<?php

namespace Tests\Feature\Actions\Products;

use App\Actions\Products\SetProductActive;
use App\Exceptions\Products\InvalidProduct;
use App\Models\ProductCategory;
use App\Models\TransactionItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SetProductActiveTest extends ProductActionTestCase
{
    public function test_product_can_be_deactivated_and_reactivated_under_active_category(): void
    {
        $product = $this->createManagedProduct();

        $inactive = app(SetProductActive::class)->execute(
            $product,
            ['is_active' => false]
        );
        $active = app(SetProductActive::class)->execute(
            $inactive,
            ['is_active' => true]
        );

        $this->assertFalse($inactive->is_active);
        $this->assertTrue($active->is_active);
    }

    public function test_deactivation_is_allowed_under_inactive_category(): void
    {
        $category = $this->category();
        $product = $this->createManagedProduct($category);
        $category->update(['is_active' => false]);

        $updated = app(SetProductActive::class)->execute(
            $product,
            ['is_active' => false]
        );

        $this->assertFalse($updated->is_active);
        $this->assertFalse($category->fresh()?->is_active);
    }

    public function test_activation_rejects_inactive_category_without_reactivating_it(): void
    {
        $category = $this->category();
        $product = $this->createManagedProduct($category, [
            'is_active' => false,
        ]);
        $category->update(['is_active' => false]);

        try {
            app(SetProductActive::class)->execute(
                $product,
                ['is_active' => true]
            );
            $this->fail('Expected activation rejection.');
        } catch (InvalidProduct $exception) {
            $this->assertStringContainsString(
                'active, non-deleted category',
                $exception->getMessage()
            );
        }

        $this->assertFalse($product->fresh()?->is_active);
        $this->assertFalse($category->fresh()?->is_active);
    }

    public function test_activation_rejects_deleted_category_without_restoring_it(): void
    {
        $category = $this->category();
        $product = $this->createManagedProduct($category, [
            'is_active' => false,
        ]);
        DB::table('product_categories')
            ->where('id', $category->id)
            ->update(['deleted_at' => now()]);

        $this->expectException(InvalidProduct::class);

        try {
            app(SetProductActive::class)->execute(
                $product,
                ['is_active' => true]
            );
        } finally {
            $this->assertFalse($product->fresh()?->is_active);
            $this->assertNotNull(
                ProductCategory::withTrashed()
                    ->findOrFail($category->id)
                    ->deleted_at
            );
        }
    }

    public function test_status_no_ops_do_not_touch_updated_timestamp(): void
    {
        $product = $this->createManagedProduct();
        $timestamp = CarbonImmutable::parse('2026-01-01 00:00:00');
        DB::table('products')
            ->where('id', $product->id)
            ->update(['updated_at' => $timestamp]);
        $product->refresh();

        app(SetProductActive::class)->execute(
            $product,
            ['is_active' => true]
        );

        $this->assertTrue(
            $product->fresh()?->updated_at?->equalTo($timestamp)
        );

        $inactive = app(SetProductActive::class)->execute(
            $product,
            ['is_active' => false]
        );
        DB::table('products')
            ->where('id', $product->id)
            ->update(['updated_at' => $timestamp]);
        $inactive->refresh();

        app(SetProductActive::class)->execute(
            $inactive,
            ['is_active' => false]
        );

        $this->assertTrue(
            $inactive->fresh()?->updated_at?->equalTo($timestamp)
        );
    }

    public function test_status_change_preserves_category_and_transaction_snapshots(): void
    {
        $product = $this->createManagedProduct();
        $item = TransactionItem::factory()->for($product)->create();
        $categoryState = $product->category->only([
            'name',
            'slug',
            'is_active',
            'deleted_at',
        ]);
        $itemState = $item->getAttributes();

        app(SetProductActive::class)->execute(
            $product,
            ['is_active' => false]
        );

        $this->assertSame(
            $categoryState,
            $product->category->fresh()?->only(
                array_keys($categoryState)
            )
        );
        $this->assertEquals($itemState, $item->fresh()?->getAttributes());
    }

    public function test_status_action_rejects_unrelated_or_non_boolean_input(): void
    {
        $product = $this->createManagedProduct();

        foreach ([
            ['is_active' => false, 'name' => 'No'],
            ['is_active' => '1'],
            [],
        ] as $input) {
            try {
                app(SetProductActive::class)->execute($product, $input);
                $this->fail('Expected status input rejection.');
            } catch (InvalidProduct) {
                $this->assertTrue($product->fresh()?->is_active);
            }
        }
    }

    public function test_status_change_locks_product_and_activation_locks_category(): void
    {
        $product = $this->createManagedProduct(overrides: [
            'is_active' => false,
        ]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(SetProductActive::class)->execute(
            $product,
            ['is_active' => true]
        );
        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->map(fn (string $query): string => strtolower($query));

        DB::disableQueryLog();

        $this->assertTrue($queries->contains(
            fn (string $query): bool => str_contains(
                $query,
                'from "products"'
            ) && str_contains($query, 'for update')
        ));
        $this->assertTrue($queries->contains(
            fn (string $query): bool => str_contains(
                $query,
                'from "product_categories"'
            ) && str_contains($query, 'for update')
        ));
    }
}
