<?php

namespace Tests\Feature\Models;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAvailabilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_contains_only_active_products_under_active_categories(): void
    {
        $activeCategory = ProductCategory::factory()->create();
        $inactiveCategory = ProductCategory::factory()
            ->inactive()
            ->create();
        $deletedCategory = ProductCategory::factory()->create();
        $available = Product::factory()
            ->for($activeCategory, 'category')
            ->create(['is_active' => true]);
        $inactiveProduct = Product::factory()
            ->for($activeCategory, 'category')
            ->inactive()
            ->create();
        $underInactiveCategory = Product::factory()
            ->for($inactiveCategory, 'category')
            ->create(['is_active' => true]);
        $underDeletedCategory = Product::factory()
            ->for($deletedCategory, 'category')
            ->create(['is_active' => true]);
        $deletedProduct = Product::factory()
            ->for($activeCategory, 'category')
            ->create(['is_active' => true]);
        $deletedProduct->delete();
        $deletedCategory->delete();

        $products = Product::query()
            ->availableForTransaction()
            ->orderBy('id')
            ->get();

        $this->assertSame([$available->id], $products->pluck('id')->all());
        $this->assertFalse($products->sole()->relationLoaded('category'));
        $this->assertNotContains(
            $inactiveProduct->id,
            $products->pluck('id')->all()
        );
        $this->assertNotContains(
            $underInactiveCategory->id,
            $products->pluck('id')->all()
        );
        $this->assertNotContains(
            $underDeletedCategory->id,
            $products->pluck('id')->all()
        );
    }

    public function test_scope_composes_with_where_in_selected_columns_and_eager_loading(): void
    {
        $category = ProductCategory::factory()->create([
            'name' => 'Selectable Category',
        ]);
        $available = Product::factory()
            ->for($category, 'category')
            ->create(['name' => 'Selectable Product']);
        $inactive = Product::factory()->inactive()->create();

        $products = Product::query()
            ->availableForTransaction()
            ->select(['id', 'product_category_id', 'name'])
            ->whereIn('id', [$available->id, $inactive->id])
            ->with([
                'category' => fn ($query) => $query
                    ->select(['id', 'name']),
            ])
            ->get();

        $this->assertCount(1, $products);
        $this->assertSame($available->id, $products->sole()->id);
        $this->assertSame(
            'Selectable Category',
            $products->sole()->category->name
        );
    }
}
