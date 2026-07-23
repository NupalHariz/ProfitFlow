<?php

namespace App\Actions\ProductCategories;

use App\Exceptions\ProductCategories\InvalidProductCategory;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

final class SetProductCategoryActive
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        ProductCategory $category,
        array $data
    ): ProductCategory {
        $unsupported = array_diff(array_keys($data), ['is_active']);

        if ($unsupported !== []) {
            throw new InvalidProductCategory(
                'Unsupported product category status fields: '
                .implode(', ', $unsupported).'.'
            );
        }

        if (! array_key_exists('is_active', $data)
            || ! is_bool($data['is_active'])) {
            throw new InvalidProductCategory(
                'Product category is_active must be a boolean.'
            );
        }

        return DB::transaction(function () use (
            $category,
            $data
        ): ProductCategory {
            $lockedCategory = ProductCategory::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedCategory === null) {
                throw new InvalidProductCategory(
                    'Product category does not exist.'
                );
            }

            $lockedCategory->is_active = $data['is_active'];
            $lockedCategory->save();

            return $lockedCategory->refresh();
        });
    }
}
