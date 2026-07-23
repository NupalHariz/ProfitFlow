<?php

namespace App\Actions\Products;

use App\Exceptions\Products\InvalidProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

final class SetProductActive
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Product $product, array $data): Product
    {
        $unsupported = array_diff(array_keys($data), ['is_active']);

        if ($unsupported !== []) {
            throw new InvalidProduct(
                'Unsupported product status fields: '
                .implode(', ', $unsupported).'.'
            );
        }

        if (! array_key_exists('is_active', $data)
            || ! is_bool($data['is_active'])) {
            throw new InvalidProduct(
                'Product is_active must be a boolean.'
            );
        }

        return DB::transaction(function () use (
            $product,
            $data
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

            if ($lockedProduct->is_active === $data['is_active']) {
                return $lockedProduct;
            }

            if ($data['is_active']) {
                $category = ProductCategory::query()
                    ->whereKey($lockedProduct->product_category_id)
                    ->lockForUpdate()
                    ->first();

                if ($category === null || ! $category->is_active) {
                    throw new InvalidProduct(
                        'Product activation requires an active, '
                        .'non-deleted category.'
                    );
                }
            }

            $lockedProduct->is_active = $data['is_active'];
            $lockedProduct->save();

            return $lockedProduct->refresh();
        });
    }
}
