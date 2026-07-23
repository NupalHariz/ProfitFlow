<?php

namespace Tests\Feature\Http\Products;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class ProductManagementTest extends ProductHttpTestCase
{
    public function test_create_page_includes_only_active_non_deleted_categories(): void
    {
        $active = $this->category([
            'name' => 'Active Category',
            'is_active' => true,
        ]);
        $this->category([
            'name' => 'Inactive Category',
            'is_active' => false,
        ]);
        $deleted = $this->category([
            'name' => 'Deleted Category',
            'is_active' => true,
        ]);
        $deleted->delete();

        $this->actingAs($this->user())
            ->get(route('products.create'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('categories', 1)
                    ->where('categories.0.id', $active->id)
                    ->where('categories.0.is_active', true)
                    ->where('category_option_limit', 250)
            );
    }

    public function test_verified_user_can_create_normalized_product(): void
    {
        $category = $this->category();

        $response = $this->actingAs($this->user())
            ->post(route('products.store'), $this->productPayload(
                $category,
                [
                    'name' => '  Professional Plan  ',
                    'sku' => '  PLAN-PRO-001  ',
                    'description' => '  Professional subscription.  ',
                    'default_selling_price' => '150000',
                    'default_production_cost' => '90000.5',
                    'is_active' => false,
                ]
            ));
        $product = Product::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('products.edit', $product));
        $this->assertSame('Professional Plan', $product->name);
        $this->assertSame('PLAN-PRO-001', $product->sku);
        $this->assertSame(
            '150000.00',
            $product->getRawOriginal('default_selling_price')
        );
        $this->assertSame(
            '90000.50',
            $product->getRawOriginal('default_production_cost')
        );
        $this->assertFalse($product->is_active);
    }

    public function test_store_rejects_inactive_deleted_and_nonexistent_categories(): void
    {
        $inactive = $this->category(['is_active' => false]);
        $deleted = $this->category();
        $deleted->delete();
        $user = $this->user();

        $this->actingAs($user)
            ->post(
                route('products.store'),
                $this->productPayload($inactive)
            )
            ->assertSessionHasErrors('product');
        $this->actingAs($user)
            ->post(
                route('products.store'),
                $this->productPayload($deleted, [
                    'sku' => 'DELETED-CATEGORY',
                ])
            )
            ->assertSessionHasErrors('product_category_id');
        $this->actingAs($user)
            ->post(
                route('products.store'),
                $this->productPayload($inactive, [
                    'product_category_id' => 999999,
                    'sku' => 'MISSING-CATEGORY',
                ])
            )
            ->assertSessionHasErrors('product_category_id');

        $this->assertDatabaseCount('products', 0);
        $this->assertFalse($inactive->fresh()?->is_active);
        $this->assertNotNull(
            ProductCategory::withTrashed()
                ->findOrFail($deleted->id)
                ->deleted_at
        );
    }

    #[DataProvider('invalidMoneyValues')]
    public function test_store_rejects_invalid_money_with_field_error(
        mixed $value
    ): void {
        $category = $this->category();

        $this->actingAs($this->user())
            ->post(
                route('products.store'),
                $this->productPayload($category, [
                    'default_selling_price' => $value,
                ])
            )
            ->assertSessionHasErrors('default_selling_price');

        $this->assertDatabaseCount('products', 0);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidMoneyValues(): array
    {
        return [
            'negative' => ['-1.00'],
            'precision' => ['1.001'],
            'scientific' => ['1e3'],
            'comma' => ['1,000.00'],
            'symbol' => ['Rp1000.00'],
            'whitespace' => [' 1.00'],
            'overflow' => ['10000000000000000.00'],
            'float' => [1.25],
        ];
    }

    public function test_zero_maximum_and_selling_below_cost_are_accepted_over_http(): void
    {
        $category = $this->category();
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('products.store'), $this->productPayload(
                $category,
                [
                    'sku' => 'ZERO',
                    'default_selling_price' => '0',
                    'default_production_cost' => '0.00',
                ]
            ))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('products.store'), $this->productPayload(
                $category,
                [
                    'sku' => 'MAX',
                    'default_selling_price' => '9999999999999999.99',
                    'default_production_cost' => '9999999999999999.99',
                ]
            ))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('products.store'), $this->productPayload(
                $category,
                [
                    'sku' => 'LOSS',
                    'default_selling_price' => '10.00',
                    'default_production_cost' => '20.00',
                ]
            ))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('products', 3);
    }

    public function test_exact_and_soft_deleted_duplicate_sku_are_field_errors_but_case_variant_is_allowed(): void
    {
        $category = $this->category();
        $product = $this->product($category, [
            'sku' => 'EXACT-SKU',
        ]);
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('products.store'), $this->productPayload(
                $category,
                ['sku' => 'EXACT-SKU']
            ))
            ->assertSessionHasErrors('sku');
        $product->delete();
        $this->actingAs($user)
            ->post(route('products.store'), $this->productPayload(
                $category,
                ['sku' => 'EXACT-SKU']
            ))
            ->assertSessionHasErrors('sku');
        $this->actingAs($user)
            ->post(route('products.store'), $this->productPayload(
                $category,
                ['sku' => 'exact-sku']
            ))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['sku' => 'exact-sku']);
    }

    public function test_sku_constraint_race_is_mapped_to_sku_field_after_transaction_rollback(): void
    {
        $category = $this->category();
        $injected = false;

        Product::creating(function (Product $creating) use (
            &$injected
        ): void {
            if ($injected) {
                return;
            }

            $injected = true;
            DB::table('products')->insert([
                'product_category_id' => $creating
                    ->product_category_id,
                'name' => 'Concurrent Product',
                'sku' => $creating->sku,
                'description' => null,
                'default_selling_price' => '1.00',
                'default_production_cost' => '0.00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        });

        try {
            $this->actingAs($this->user())
                ->post(
                    route('products.store'),
                    $this->productPayload($category)
                )
                ->assertSessionHasErrors('sku');
        } finally {
            Product::flushEventListeners();
        }

        $this->assertDatabaseCount('products', 0);
    }

    public function test_store_rejects_protected_and_unknown_fields(): void
    {
        $category = $this->category();

        $this->actingAs($this->user())
            ->post(route('products.store'), [
                ...$this->productPayload($category),
                'id' => 999,
                'deleted_at' => now(),
                'category' => ['id' => $category->id],
                'transaction_items' => [['id' => 1]],
                'unknown' => 'ignored by Laravel',
            ])
            ->assertSessionHasErrors([
                'id',
                'deleted_at',
                'category',
                'transaction_items',
            ]);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_edit_includes_active_options_and_always_keeps_current_inactive_category(): void
    {
        $current = $this->category([
            'name' => 'Current Inactive',
            'is_active' => false,
        ]);
        $active = $this->category([
            'name' => 'Available Active',
            'is_active' => true,
        ]);
        $unrelatedInactive = $this->category([
            'name' => 'Unrelated Inactive',
            'is_active' => false,
        ]);
        $product = $this->product($current, ['is_active' => true]);
        $before = DB::table('products')
            ->where('id', $product->id)
            ->first();

        $this->actingAs($this->user())
            ->get(route('products.edit', $product))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('product.category.id', $current->id)
                    ->where('product.category.is_active', false)
                    ->where(
                        'categories',
                        fn (mixed $options): bool => collect($options)
                            ->pluck('id')
                            ->sort()
                            ->values()
                            ->all() === collect([
                                $current->id,
                                $active->id,
                            ])->sort()->values()->all()
                    )
                    ->where(
                        'categories',
                        fn (mixed $options): bool => ! collect($options)
                            ->pluck('id')
                            ->contains($unrelatedInactive->id)
                    )
            );

        $this->assertEquals(
            $before,
            DB::table('products')
                ->where('id', $product->id)
                ->first()
        );
        $this->assertFalse($current->fresh()?->is_active);
    }

    public function test_general_update_preserves_status_and_allows_same_inactive_category(): void
    {
        $category = $this->category(['is_active' => false]);
        $product = $this->product($category, ['is_active' => true]);

        $this->actingAs($this->user())
            ->patch(
                route('products.update', $product),
                $this->productUpdatePayload($product, [
                    'name' => '  Updated Name  ',
                    'default_selling_price' => '50',
                    'default_production_cost' => '75.5',
                ])
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('products.edit', $product));
        $fresh = $product->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame('Updated Name', $fresh->name);
        $this->assertTrue($fresh->is_active);
        $this->assertSame($category->id, $fresh->product_category_id);
        $this->assertFalse($category->fresh()?->is_active);
    }

    public function test_general_update_requires_active_category_only_for_reassignment(): void
    {
        $product = $this->product();
        $active = $this->category(['is_active' => true]);
        $inactive = $this->category(['is_active' => false]);
        $user = $this->user();

        $this->actingAs($user)
            ->patch(
                route('products.update', $product),
                $this->productUpdatePayload($product, [
                    'product_category_id' => $active->id,
                ])
            )
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->patch(
                route('products.update', $product),
                $this->productUpdatePayload($product, [
                    'product_category_id' => $inactive->id,
                    'name' => 'Should Roll Back',
                ])
            )
            ->assertSessionHasErrors('product');

        $fresh = $product->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame($active->id, $fresh->product_category_id);
        $this->assertNotSame('Should Roll Back', $fresh->name);
        $this->assertFalse($inactive->fresh()?->is_active);
    }

    public function test_general_update_rejects_status_and_protected_fields(): void
    {
        $product = $this->product();

        $this->actingAs($this->user())
            ->patch(route('products.update', $product), [
                ...$this->productUpdatePayload($product),
                'is_active' => false,
                'deleted_at' => now(),
            ])
            ->assertSessionHasErrors(['is_active', 'deleted_at']);

        $this->assertTrue($product->fresh()?->is_active);
    }

    public function test_status_endpoint_obeys_conditional_category_rules_and_rejects_other_fields(): void
    {
        $category = $this->category();
        $product = $this->product($category, ['is_active' => true]);
        $user = $this->user();

        $this->actingAs($user)
            ->patch(
                route('products.update-status', $product),
                ['is_active' => false]
            )
            ->assertSessionHasNoErrors();
        $category->update(['is_active' => false]);
        $this->actingAs($user)
            ->patch(
                route('products.update-status', $product),
                ['is_active' => true]
            )
            ->assertSessionHasErrors('product');
        $this->actingAs($user)
            ->patch(
                route('products.update-status', $product),
                [
                    'is_active' => false,
                    'name' => 'Not allowed',
                ]
            )
            ->assertSessionHasErrors('name');

        $this->assertFalse($product->fresh()?->is_active);
        $this->assertFalse($category->fresh()?->is_active);
    }

    public function test_referenced_product_can_be_soft_deleted_over_http_without_snapshot_mutation(): void
    {
        $product = $this->product();
        $item = TransactionItem::factory()->for($product)->create();
        $snapshot = $item->getAttributes();

        $this->actingAs($this->user())
            ->delete(route('products.destroy', $product))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('products.index'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertEquals($snapshot, $item->fresh()?->getAttributes());
    }
}
