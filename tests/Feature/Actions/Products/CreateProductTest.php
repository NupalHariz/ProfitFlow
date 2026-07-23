<?php

namespace Tests\Feature\Actions\Products;

use App\Actions\Products\CreateProduct;
use App\Exceptions\Products\InvalidProduct;
use App\Exceptions\Products\ProductSkuAlreadyExists;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class CreateProductTest extends ProductActionTestCase
{
    public function test_product_is_created_with_normalized_strings_and_canonical_money(): void
    {
        $category = $this->category();
        $product = $this->createManagedProduct($category, [
            'name' => '  Professional Plan  ',
            'sku' => '  PLAN-PRO-001  ',
            'description' => '  Subscription product.  ',
            'default_selling_price' => '150000',
            'default_production_cost' => '90000.5',
        ]);

        $this->assertSame($category->id, $product->product_category_id);
        $this->assertSame('Professional Plan', $product->name);
        $this->assertSame('PLAN-PRO-001', $product->sku);
        $this->assertSame('Subscription product.', $product->description);
        $this->assertSame(
            '150000.00',
            $product->getRawOriginal('default_selling_price')
        );
        $this->assertSame(
            '90000.50',
            $product->getRawOriginal('default_production_cost')
        );
    }

    public function test_blank_description_is_normalized_to_null(): void
    {
        $product = $this->createManagedProduct(
            overrides: ['description' => '   ']
        );

        $this->assertNull($product->description);
    }

    public function test_inactive_product_still_requires_an_active_category(): void
    {
        $category = $this->category(['is_active' => true]);

        $product = $this->createManagedProduct($category, [
            'is_active' => false,
        ]);

        $this->assertFalse($product->is_active);
    }

    #[DataProvider('unavailableCategoryStates')]
    public function test_creation_rejects_unavailable_category(
        string $state
    ): void {
        $category = $this->category([
            'is_active' => $state !== 'inactive',
        ]);

        if ($state === 'deleted') {
            $category->delete();
        }

        $categoryId = $state === 'missing'
            ? $category->id + 10_000
            : $category->id;

        try {
            app(CreateProduct::class)->execute(
                $this->productData($category, [
                    'product_category_id' => $categoryId,
                    'is_active' => false,
                ])
            );
            $this->fail('Expected unavailable category rejection.');
        } catch (InvalidProduct $exception) {
            $this->assertStringContainsString(
                'active, non-deleted category',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('products', 0);
        $this->assertSame(
            $state !== 'inactive',
            ProductCategory::withTrashed()
                ->findOrFail($category->id)
                ->is_active
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unavailableCategoryStates(): array
    {
        return [
            'inactive' => ['inactive'],
            'soft deleted' => ['deleted'],
            'nonexistent' => ['missing'],
        ];
    }

    public function test_zero_maximum_and_loss_making_defaults_are_allowed(): void
    {
        $zero = $this->createManagedProduct(overrides: [
            'sku' => 'ZERO',
            'default_selling_price' => '0',
            'default_production_cost' => '0.00',
        ]);
        $maximum = $this->createManagedProduct(overrides: [
            'sku' => 'MAXIMUM',
            'default_selling_price' => '9999999999999999.99',
            'default_production_cost' => '9999999999999999.99',
        ]);
        $loss = $this->createManagedProduct(overrides: [
            'sku' => 'LOSS',
            'default_selling_price' => '10.00',
            'default_production_cost' => '20.00',
        ]);

        $this->assertSame(
            '0.00',
            $zero->getRawOriginal('default_selling_price')
        );
        $this->assertSame(
            '9999999999999999.99',
            $maximum->getRawOriginal('default_selling_price')
        );
        $this->assertSame(
            '10.00',
            $loss->getRawOriginal('default_selling_price')
        );
        $this->assertSame(
            '20.00',
            $loss->getRawOriginal('default_production_cost')
        );
    }

    #[DataProvider('invalidMoneyValues')]
    public function test_invalid_money_is_rejected_without_float_coercion(
        mixed $value
    ): void {
        $this->expectException(InvalidProduct::class);

        $this->createManagedProduct(overrides: [
            'default_selling_price' => $value,
        ]);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidMoneyValues(): array
    {
        return [
            'negative' => ['-1.00'],
            'more than two decimals' => ['1.001'],
            'scientific notation' => ['1e3'],
            'comma' => ['1,000.00'],
            'currency symbol' => ['Rp 1000.00'],
            'leading whitespace' => [' 1.00'],
            'trailing whitespace' => ['1.00 '],
            'overflow' => ['10000000000000000.00'],
            'float input' => [1.25],
            'integer input' => [1],
        ];
    }

    #[DataProvider('invalidIdentityValues')]
    public function test_invalid_name_and_sku_are_rejected(
        string $field,
        mixed $value
    ): void {
        $this->expectException(InvalidProduct::class);

        $this->createManagedProduct(overrides: [$field => $value]);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidIdentityValues(): array
    {
        return [
            'blank name' => ['name', '   '],
            'long name' => ['name', str_repeat('n', 161)],
            'non-string name' => ['name', 123],
            'blank sku' => ['sku', '   '],
            'long sku' => ['sku', str_repeat('s', 81)],
            'non-string sku' => ['sku', 123],
        ];
    }

    public function test_unknown_or_client_owned_fields_are_rejected(): void
    {
        $category = $this->category();

        $this->expectException(InvalidProduct::class);
        $this->expectExceptionMessage('Unsupported product fields');

        app(CreateProduct::class)->execute([
            ...$this->productData($category),
            'deleted_at' => now(),
        ]);
    }

    public function test_exact_duplicate_and_soft_deleted_sku_are_reserved(): void
    {
        $this->createManagedProduct();

        try {
            $this->createManagedProduct();
            $this->fail('Expected exact duplicate SKU rejection.');
        } catch (ProductSkuAlreadyExists $exception) {
            $this->assertStringContainsString(
                'PLAN-PRO-001',
                $exception->getMessage()
            );
        }

        Product::query()->firstOrFail()->delete();

        $this->expectException(ProductSkuAlreadyExists::class);
        $this->createManagedProduct();
    }

    public function test_sku_uniqueness_is_case_sensitive(): void
    {
        $upper = $this->createManagedProduct();
        $lower = $this->createManagedProduct(overrides: [
            'sku' => 'plan-pro-001',
        ]);

        $this->assertSame('PLAN-PRO-001', $upper->sku);
        $this->assertSame('plan-pro-001', $lower->sku);
    }

    public function test_named_database_unique_constraint_is_a_safety_layer(): void
    {
        $category = $this->category();
        Product::factory()
            ->for($category, 'category')
            ->create(['sku' => 'DATABASE-GUARD']);

        $this->expectException(QueryException::class);

        Product::factory()
            ->for($category, 'category')
            ->create(['sku' => 'DATABASE-GUARD']);
    }

    public function test_unrelated_database_errors_are_rethrown(): void
    {
        Product::creating(function (): void {
            DB::statement('select * from missing_product_test_table');
        });

        try {
            $this->expectException(QueryException::class);
            $this->expectExceptionMessage('missing_product_test_table');

            $this->createManagedProduct();
        } finally {
            Product::flushEventListeners();
        }
    }

    public function test_creation_locks_the_category_row(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->createManagedProduct();
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertTrue(collect($queries)->contains(
            fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "product_categories"'
            ) && str_contains(strtolower($query['query']), 'for update')
        ));
    }
}
