<?php

namespace Tests\Feature\Http\Products;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ProductHttpTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', config('database.default'));
    }

    protected function user(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function category(array $attributes = []): ProductCategory
    {
        return ProductCategory::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function product(
        ?ProductCategory $category = null,
        array $attributes = []
    ): Product {
        $category ??= $this->category();

        return Product::factory()
            ->for($category, 'category')
            ->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function productPayload(
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
    protected function productUpdatePayload(
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
