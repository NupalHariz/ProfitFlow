<?php

namespace Tests\Feature\Http\ProductCategories;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class ProductCategoryIndexTest extends ProductCategoryHttpTestCase
{
    public function test_index_is_paginated_fifteen_per_page(): void
    {
        ProductCategory::factory()->count(16)->create();

        $this->actingAs($this->user())
            ->get(route('product-categories.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('categories.data', 15)
                    ->where('categories.meta.current_page', 1)
                    ->where('categories.meta.last_page', 2)
                    ->where('categories.meta.per_page', 15)
                    ->where('categories.meta.total', 16)
            );
    }

    public function test_search_is_case_insensitive_across_name_and_description(): void
    {
        $this->category([
            'name' => 'Home Essentials',
            'description' => 'Domestic products.',
        ]);
        $this->category([
            'name' => 'Technology',
            'description' => 'Premium OFFICE equipment.',
        ]);
        $this->category([
            'name' => 'Outdoor',
            'description' => 'Garden supplies.',
        ]);

        $this->actingAs($this->user())
            ->get(route('product-categories.index', [
                'search' => 'hOmE',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('categories.data', 1)
                    ->where('categories.data.0.name', 'Home Essentials')
            );

        $this->actingAs($this->user())
            ->get(route('product-categories.index', [
                'search' => 'office',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('categories.data', 1)
                    ->where('categories.data.0.name', 'Technology')
            );
    }

    public function test_active_and_inactive_filters_are_database_backed(): void
    {
        $this->category([
            'name' => 'Active Category',
            'is_active' => true,
        ]);
        $this->category([
            'name' => 'Inactive Category',
            'is_active' => false,
        ]);
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('product-categories.index', ['status' => 'active']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('categories.data', 1)
                    ->where('categories.data.0.name', 'Active Category')
                    ->where('categories.data.0.is_active', true)
            );

        $this->actingAs($user)
            ->get(route('product-categories.index', ['status' => 'inactive']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('categories.data', 1)
                    ->where('categories.data.0.name', 'Inactive Category')
                    ->where('categories.data.0.is_active', false)
            );
    }

    public function test_soft_deleted_categories_are_excluded(): void
    {
        $visible = $this->category(['name' => 'Visible']);
        $deleted = $this->category(['name' => 'Deleted']);
        $deleted->delete();

        $this->actingAs($this->user())
            ->get(route('product-categories.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('categories.data', 1)
                    ->where('categories.data.0.id', $visible->id)
            );
    }

    public function test_product_counts_exclude_soft_deleted_products(): void
    {
        $category = $this->category();
        Product::factory()
            ->count(2)
            ->for($category, 'category')
            ->create();
        $deleted = Product::factory()
            ->for($category, 'category')
            ->create();
        $deleted->delete();

        $this->actingAs($this->user())
            ->get(route('product-categories.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('categories.data.0.products_count', 2)
            );
    }

    public function test_pagination_links_preserve_search_and_status_filters(): void
    {
        ProductCategory::factory()
            ->count(16)
            ->create([
                'description' => 'Office collection',
                'is_active' => true,
            ]);

        $this->actingAs($this->user())
            ->get(route('product-categories.index', [
                'search' => 'Office',
                'status' => 'active',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'categories.links.next',
                        fn (mixed $url): bool => is_string($url)
                            && str_contains($url, 'search=Office')
                            && str_contains($url, 'status=active')
                            && str_contains($url, 'page=2')
                    )
            );
    }

    public function test_index_props_are_explicitly_bounded(): void
    {
        $category = $this->category();
        Product::factory()
            ->for($category, 'category')
            ->create();

        $this->actingAs($this->user())
            ->get(route('product-categories.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has(
                        'categories.data.0',
                        fn (Assert $category) => $category
                            ->hasAll([
                                'id',
                                'name',
                                'slug',
                                'description',
                                'is_active',
                                'products_count',
                                'created_at',
                                'updated_at',
                            ])
                            ->missing('products')
                            ->missing('profit_scheme_versions')
                            ->missing('deleted_at')
                    )
            );
    }

    public function test_index_query_count_does_not_grow_with_category_rows(): void
    {
        ProductCategory::factory()
            ->count(30)
            ->has(Product::factory()->count(2))
            ->create();
        $user = $this->user();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)
            ->get(route('product-categories.index'))
            ->assertOk();
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(7, $queryCount);
    }
}
