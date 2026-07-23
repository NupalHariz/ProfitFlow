<?php

namespace Tests\Feature\Actions\Products;

use App\Actions\Products\CreateProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ProductActionTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', config('database.default'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function category(array $attributes = []): ProductCategory
    {
        return ProductCategory::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createManagedProduct(
        ?ProductCategory $category = null,
        array $overrides = []
    ): Product {
        $category ??= $this->category();

        return app(CreateProduct::class)->execute(
            array_replace(
                $this->productData($category),
                $overrides
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function productData(
        ProductCategory $category,
        array $overrides = []
    ): array {
        return array_replace([
            'product_category_id' => $category->id,
            'name' => 'Professional Plan',
            'sku' => 'PLAN-PRO-001',
            'description' => 'Professional subscription.',
            'default_selling_price' => '150000.00',
            'default_production_cost' => '90000.00',
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function updateData(
        Product $product,
        array $overrides = []
    ): array {
        return array_replace([
            'product_category_id' => $product->product_category_id,
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'default_selling_price' => $product
                ->getRawOriginal('default_selling_price'),
            'default_production_cost' => $product
                ->getRawOriginal('default_production_cost'),
        ], $overrides);
    }
}
