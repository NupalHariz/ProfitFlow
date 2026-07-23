<?php

namespace App\Actions\Products;

use App\Actions\Products\Support\ProductInputNormalizer;
use App\Exceptions\Products\InvalidProduct;
use App\Exceptions\Products\ProductSkuAlreadyExists;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CreateProduct
{
    public function __construct(
        private readonly ProductInputNormalizer $normalizer
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Product
    {
        $normalized = $this->normalizer->normalizeForCreation($data);

        try {
            return DB::transaction(function () use (
                $normalized
            ): Product {
                $category = ProductCategory::query()
                    ->whereKey($normalized['product_category_id'])
                    ->lockForUpdate()
                    ->first();

                if ($category === null || ! $category->is_active) {
                    throw new InvalidProduct(
                        'Product creation requires an active, '
                        .'non-deleted category.'
                    );
                }

                return Product::query()->create($normalized);
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
