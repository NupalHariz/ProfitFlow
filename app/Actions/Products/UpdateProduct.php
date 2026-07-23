<?php

namespace App\Actions\Products;

use App\Actions\Products\Support\ProductInputNormalizer;
use App\Exceptions\Products\InvalidProduct;
use App\Exceptions\Products\ProductSkuAlreadyExists;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class UpdateProduct
{
    public function __construct(
        private readonly ProductInputNormalizer $normalizer
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Product $product, array $data): Product
    {
        $normalized = $this->normalizer->normalizeForUpdate($data);

        try {
            return DB::transaction(function () use (
                $product,
                $normalized
            ): Product {
                $lockedProduct = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($lockedProduct === null) {
                    throw new InvalidProduct(
                        'Product does not exist.'
                    );
                }

                $categoryChanged = $lockedProduct->product_category_id
                    !== $normalized['product_category_id'];
                $targetCategory = ProductCategory::query()
                    ->whereKey($normalized['product_category_id'])
                    ->lockForUpdate()
                    ->first();

                if ($targetCategory === null) {
                    throw new InvalidProduct(
                        'Product category does not exist or has been deleted.'
                    );
                }

                if ($categoryChanged && ! $targetCategory->is_active) {
                    throw new InvalidProduct(
                        'Product reassignment requires an active category.'
                    );
                }

                $lockedProduct->fill($normalized);
                $lockedProduct->save();

                return $lockedProduct->refresh();
            });
        } catch (QueryException $exception) {
            if ($this->isSkuUniqueViolation($exception)) {
                throw new ProductSkuAlreadyExists(
                    "Product SKU [{$normalized['sku']}] already exists.",
                    previous: $exception
                );
            }

            throw $exception;
        }
    }

    private function isSkuUniqueViolation(
        QueryException $exception
    ): bool {
        return (string) $exception->getCode() === '23505'
            && str_contains(
                $exception->getMessage(),
                'uq_products_sku'
            );
    }
}
