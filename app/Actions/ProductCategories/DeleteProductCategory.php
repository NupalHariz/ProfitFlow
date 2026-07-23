<?php

namespace App\Actions\ProductCategories;

use App\Exceptions\ProductCategories\InvalidProductCategory;
use App\Exceptions\ProductCategories\ProductCategoryNotDeletable;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

final class DeleteProductCategory
{
    public function execute(ProductCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            $lockedCategory = ProductCategory::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedCategory === null) {
                throw new InvalidProductCategory(
                    'Product category does not exist.'
                );
            }

            if ($lockedCategory->products()->exists()) {
                throw new ProductCategoryNotDeletable(
                    'Product category cannot be deleted while products '
                    .'still belong to it. Deactivate the category instead.'
                );
            }

            if ($lockedCategory->profitSchemeVersions()->exists()) {
                throw new ProductCategoryNotDeletable(
                    'Product category cannot be deleted because '
                    .'profit-sharing versions reference it. Deactivate '
                    .'the category instead.'
                );
            }

            $lockedCategory->delete();
        });
    }
}
