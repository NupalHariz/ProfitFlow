<?php

namespace Tests\Feature\Http\Products;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class ProductIndexTest extends ProductHttpTestCase
{
    public function test_index_is_paginated_fifteen_per_page_in_stable_order(): void
    {
        $category = $this->category();
        Product::factory()
            ->count(16)
            ->for($category, 'category')
            ->sequence(
                fn ($sequence): array => [
                    'name' => sprintf('Product %02d', 16 - $sequence->index),
                ]
            )
            ->create();

        $this->actingAs($this->user())
            ->get(route('products.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('products.data', 15)
                    ->where('products.data.0.name', 'Product 01')
                    ->where('products.meta.current_page', 1)
                    ->where('products.meta.last_page', 2)
                    ->where('products.meta.per_page', 15)
                    ->where('products.meta.total', 16)
            );
    }

    public function test_search_is_case_insensitive_across_name_and_sku(): void
    {
        $this->product(attributes: [
            'name' => 'Enterprise Workspace',
            'sku' => 'PLAN-ENT',
        ]);
        $this->product(attributes: [
            'name' => 'Basic Product',
            'sku' => 'OFFICE-BASIC',
        ]);
        $this->product(attributes: [
            'name' => 'Unrelated',
            'sku' => 'OTHER',
        ]);
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('products.index', ['search' => 'eNtErPrIsE']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('products.data', 1)
                    ->where(
                        'products.data.0.name',
                        'Enterprise Workspace'
                    )
            );
        $this->actingAs($user)
            ->get(route('products.index', ['search' => 'office']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('products.data', 1)
                    ->where('products.data.0.sku', 'OFFICE-BASIC')
            );
    }

    public function test_category_and_status_filters_are_database_backed(): void
    {
        $firstCategory = $this->category(['name' => 'First']);
        $secondCategory = $this->category(['name' => 'Second']);
        $active = $this->product($firstCategory, [
            'name' => 'Matching Active',
            'is_active' => true,
        ]);
        $this->product($firstCategory, [
            'name' => 'Matching Inactive',
            'is_active' => false,
        ]);
        $this->product($secondCategory, [
            'name' => 'Other Active',
            'is_active' => true,
        ]);

        $this->actingAs($this->user())
            ->get(route('products.index', [
                'category' => $firstCategory->id,
                'status' => 'active',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('products.data', 1)
                    ->where('products.data.0.id', $active->id)
                    ->where(
                        'filters.category',
                        $firstCategory->id
                    )
                    ->where('filters.status', 'active')
            );
    }

    public function test_soft_deleted_products_are_excluded(): void
    {
        $visible = $this->product(attributes: ['name' => 'Visible']);
        $deleted = $this->product(attributes: ['name' => 'Deleted']);
        $deleted->delete();

        $this->actingAs($this->user())
            ->get(route('products.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('products.data', 1)
                    ->where('products.data.0.id', $visible->id)
            );
    }

    public function test_index_payload_is_explicit_and_contains_category_summary_only(): void
    {
        $this->product(attributes: [
            'default_selling_price' => '150000.00',
            'default_production_cost' => '90000.00',
        ]);

        $this->actingAs($this->user())
            ->get(route('products.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has(
                        'products.data.0',
                        fn (Assert $product) => $product
                            ->hasAll([
                                'id',
                                'name',
                                'sku',
                                'description',
                                'default_selling_price',
                                'default_production_cost',
                                'is_active',
                                'updated_at',
                                'category',
                            ])
                            ->where(
                                'default_selling_price',
                                '150000.00'
                            )
                            ->where(
                                'default_production_cost',
                                '90000.00'
                            )
                            ->has(
                                'category',
                                fn (Assert $category) => $category
                                    ->hasAll(['id', 'name', 'is_active'])
                                    ->missing('description')
                                    ->missing('slug')
                                    ->missing('deleted_at')
                            )
                            ->missing('transaction_items')
                            ->missing('deleted_at')
                            ->missing('created_at')
                    )
            );
    }

    public function test_pagination_links_preserve_all_filters(): void
    {
        $category = $this->category();
        Product::factory()
            ->count(16)
            ->for($category, 'category')
            ->create([
                'name' => 'Office Product',
                'is_active' => true,
            ]);

        $this->actingAs($this->user())
            ->get(route('products.index', [
                'search' => 'Office',
                'category' => $category->id,
                'status' => 'active',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'products.links.next',
                        fn (mixed $url): bool => is_string($url)
                            && str_contains($url, 'search=Office')
                            && str_contains(
                                $url,
                                'category='.$category->id
                            )
                            && str_contains($url, 'status=active')
                            && str_contains($url, 'page=2')
                    )
            );
    }

    public function test_filter_category_options_are_bounded_and_keep_selected_category(): void
    {
        $selected = $this->category([
            'name' => 'ZZZ Selected',
            'is_active' => false,
        ]);
        ProductCategory::factory()->count(250)->create();

        $this->actingAs($this->user())
            ->get(route('products.index', [
                'category' => $selected->id,
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('category_options', 250)
                    ->where(
                        'category_options',
                        fn (mixed $options): bool => $options
                            instanceof Collection
                            && $options->pluck('id')->contains(
                                $selected->id
                            )
                    )
            );
    }

    public function test_index_query_count_does_not_grow_with_product_rows(): void
    {
        $category = $this->category();
        Product::factory()
            ->count(30)
            ->for($category, 'category')
            ->create();
        $user = $this->user();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk();
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(9, $queryCount);
    }
}
