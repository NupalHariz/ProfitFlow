<?php

namespace Tests\Feature\Actions\Products;

use App\Actions\Products\UpdateProduct;
use App\Exceptions\Products\InvalidProduct;
use App\Exceptions\Products\ProductSkuAlreadyExists;
use App\Models\ProductCategory;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class UpdateProductTest extends ProductActionTestCase
{
    public function test_general_update_changes_only_editable_normalized_fields(): void
    {
        $product = $this->createManagedProduct(overrides: [
            'is_active' => false,
        ]);

        $updated = app(UpdateProduct::class)->execute(
            $product,
            $this->updateData($product, [
                'name' => '  Enterprise Plan  ',
                'sku' => '  PLAN-ENT-001  ',
                'description' => '   ',
                'default_selling_price' => '250000',
                'default_production_cost' => '100000.5',
            ])
        );

        $this->assertSame('Enterprise Plan', $updated->name);
        $this->assertSame('PLAN-ENT-001', $updated->sku);
        $this->assertNull($updated->description);
        $this->assertSame(
            '250000.00',
            $updated->getRawOriginal('default_selling_price')
        );
        $this->assertSame(
            '100000.50',
            $updated->getRawOriginal('default_production_cost')
        );
        $this->assertFalse($updated->is_active);
    }

    public function test_active_product_can_keep_same_inactive_category_during_edit(): void
    {
        $category = $this->category();
        $product = $this->createManagedProduct($category);
        $category->update(['is_active' => false]);

        $updated = app(UpdateProduct::class)->execute(
            $product,
            $this->updateData($product, ['name' => 'Renamed Product'])
        );

        $this->assertSame('Renamed Product', $updated->name);
        $this->assertSame($category->id, $updated->product_category_id);
        $this->assertTrue($updated->is_active);
        $this->assertFalse($category->fresh()?->is_active);
    }

    public function test_inactive_product_can_keep_same_inactive_category_during_edit(): void
    {
        $category = $this->category();
        $product = $this->createManagedProduct($category, [
            'is_active' => false,
        ]);
        $category->update(['is_active' => false]);

        $updated = app(UpdateProduct::class)->execute(
            $product,
            $this->updateData($product, ['sku' => 'NEW-SKU'])
        );

        $this->assertSame('NEW-SKU', $updated->sku);
        $this->assertFalse($updated->is_active);
    }

    public function test_product_can_be_reassigned_to_active_category(): void
    {
        $product = $this->createManagedProduct();
        $target = $this->category(['is_active' => true]);

        $updated = app(UpdateProduct::class)->execute(
            $product,
            $this->updateData($product, [
                'product_category_id' => $target->id,
            ])
        );

        $this->assertSame($target->id, $updated->product_category_id);
    }

    #[DataProvider('unavailableTargetStates')]
    public function test_reassignment_rejects_unavailable_target_category(
        string $state
    ): void {
        $product = $this->createManagedProduct();
        $target = $this->category([
            'is_active' => $state !== 'inactive',
        ]);

        if ($state === 'deleted') {
            $target->delete();
        }

        $targetId = $state === 'missing'
            ? $target->id + 10_000
            : $target->id;

        try {
            app(UpdateProduct::class)->execute(
                $product,
                $this->updateData($product, [
                    'product_category_id' => $targetId,
                    'name' => 'Should Roll Back',
                ])
            );
            $this->fail('Expected category reassignment rejection.');
        } catch (InvalidProduct $exception) {
            $this->assertStringContainsString(
                'category',
                strtolower($exception->getMessage())
            );
        }

        $fresh = $product->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(
            $product->product_category_id,
            $fresh->product_category_id
        );
        $this->assertSame('Professional Plan', $fresh->name);
        $this->assertSame(
            $state !== 'inactive',
            $this->categoryIncludingDeleted($target->id)->is_active
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unavailableTargetStates(): array
    {
        return [
            'inactive' => ['inactive'],
            'soft deleted' => ['deleted'],
            'nonexistent' => ['missing'],
        ];
    }

    public function test_same_sku_is_allowed_but_another_product_sku_is_reserved(): void
    {
        $product = $this->createManagedProduct();

        app(UpdateProduct::class)->execute(
            $product,
            $this->updateData($product, ['name' => 'Same SKU'])
        );
        $other = $this->createManagedProduct(overrides: [
            'sku' => 'OTHER-SKU',
        ]);

        $this->expectException(ProductSkuAlreadyExists::class);

        app(UpdateProduct::class)->execute(
            $other,
            $this->updateData($other, ['sku' => 'PLAN-PRO-001'])
        );
    }

    public function test_general_update_rejects_status_and_unknown_fields(): void
    {
        $product = $this->createManagedProduct();

        try {
            app(UpdateProduct::class)->execute(
                $product,
                [
                    ...$this->updateData($product),
                    'is_active' => false,
                ]
            );
            $this->fail('Expected status field rejection.');
        } catch (InvalidProduct $exception) {
            $this->assertStringContainsString(
                'Unsupported product fields',
                $exception->getMessage()
            );
        }

        $this->assertTrue($product->fresh()?->is_active);
    }

    #[DataProvider('invalidUpdateMoney')]
    public function test_general_update_rejects_invalid_money(
        mixed $value
    ): void {
        $product = $this->createManagedProduct();

        $this->expectException(InvalidProduct::class);

        app(UpdateProduct::class)->execute(
            $product,
            $this->updateData($product, [
                'default_production_cost' => $value,
            ])
        );
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidUpdateMoney(): array
    {
        return [
            'negative' => ['-0.01'],
            'precision' => ['1.234'],
            'scientific' => ['5e2'],
            'whitespace' => [' 1.00'],
            'float' => [1.0],
        ];
    }

    public function test_master_update_does_not_mutate_existing_transaction_snapshot(): void
    {
        $product = $this->createManagedProduct();
        $item = TransactionItem::factory()
            ->for($product)
            ->create([
                'product_name_snapshot' => 'Professional Plan',
                'product_sku_snapshot' => 'PLAN-PRO-001',
                'category_name_snapshot' => $product->category->name,
                'unit_selling_price' => '150000.00',
                'unit_production_cost' => '90000.00',
            ]);
        $snapshot = $item->only([
            'product_category_id',
            'product_name_snapshot',
            'product_sku_snapshot',
            'category_name_snapshot',
            'unit_selling_price',
            'unit_production_cost',
            'total_revenue',
            'total_production_cost',
            'net_profit',
        ]);
        $target = $this->category(['name' => 'Different Category']);

        app(UpdateProduct::class)->execute(
            $product,
            $this->updateData($product, [
                'product_category_id' => $target->id,
                'name' => 'Enterprise Plan',
                'sku' => 'ENTERPRISE',
                'default_selling_price' => '1.00',
                'default_production_cost' => '0.00',
            ])
        );

        $this->assertSame($snapshot, $item->fresh()?->only(
            array_keys($snapshot)
        ));
    }

    public function test_update_locks_product_and_target_category_rows(): void
    {
        $product = $this->createManagedProduct();
        $target = $this->category();
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(UpdateProduct::class)->execute(
            $product,
            $this->updateData($product, [
                'product_category_id' => $target->id,
            ])
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

    private function categoryIncludingDeleted(int $id): mixed
    {
        return ProductCategory::withTrashed()
            ->findOrFail($id);
    }
}
