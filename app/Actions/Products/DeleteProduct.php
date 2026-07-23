<?php

namespace App\Actions\Products;

use App\Exceptions\Products\InvalidProduct;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final class DeleteProduct
{
    public function execute(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $lockedProduct = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedProduct === null) {
                throw new InvalidProduct(
                    'Product does not exist.'
                );
            }

            $lockedProduct->delete();
        });
    }
}
