<?php

namespace Tests\Feature\Http\Transactions;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class TransactionCreateTest extends TransactionHttpTestCase
{
    public function test_create_page_renders_with_bounded_lookup_metadata(): void
    {
        $this->actingAs($this->user())
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('transactions/create')
                    ->where('lookup_limit', 250)
                    ->has('customers')
                    ->has('products')
            );
    }

    public function test_only_active_products_under_active_categories_are_selectable(): void
    {
        $activeCategory = ProductCategory::factory()->create();
        $inactiveCategory = ProductCategory::factory()
            ->inactive()
            ->create();
        $deletedCategory = ProductCategory::factory()->create();
        $included = Product::factory()
            ->for($activeCategory, 'category')
            ->create(['is_active' => true]);
        $inactive = Product::factory()
            ->for($activeCategory, 'category')
            ->inactive()
            ->create();
        $underInactiveCategory = Product::factory()
            ->for($inactiveCategory, 'category')
            ->create();
        $underDeletedCategory = Product::factory()
            ->for($deletedCategory, 'category')
            ->create();
        $deleted = Product::factory()
            ->for($activeCategory, 'category')
            ->create();
        $deleted->delete();
        $deletedCategory->delete();

        $this->actingAs($this->user())
            ->get(route('transactions.create'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('products', 1)
                    ->where('products.0.id', $included->id)
                    ->where(
                        'products',
                        function (mixed $products) use (
                            $inactive,
                            $underInactiveCategory,
                            $underDeletedCategory,
                            $deleted
                        ): bool {
                            if (! $products instanceof Collection) {
                                return false;
                            }

                            $ids = $products->pluck('id')->all();

                            return ! in_array($inactive->id, $ids, true)
                                && ! in_array(
                                    $underInactiveCategory->id,
                                    $ids,
                                    true
                                )
                                && ! in_array(
                                    $underDeletedCategory->id,
                                    $ids,
                                    true
                                )
                                && ! in_array($deleted->id, $ids, true);
                        }
                    )
            );
    }

    public function test_non_deleted_customers_are_available_and_deleted_customers_are_excluded(): void
    {
        $available = Customer::factory()->create([
            'name' => 'Available Customer',
        ]);
        $deleted = Customer::factory()->create([
            'name' => 'Deleted Customer',
        ]);
        $deleted->delete();

        $this->actingAs($this->user())
            ->get(route('transactions.create'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('customers', 1)
                    ->where('customers.0.id', $available->id)
                    ->where(
                        'customers',
                        fn (mixed $customers): bool => $customers
                            instanceof Collection
                            && ! $customers
                                ->pluck('id')
                                ->contains($deleted->id)
                    )
            );
    }

    public function test_lookup_props_contain_only_needed_fields(): void
    {
        Customer::factory()->create();
        Product::factory()->create();

        $this->actingAs($this->user())
            ->get(route('transactions.create'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has(
                        'customers.0',
                        fn (Assert $customer) => $customer
                            ->hasAll(['id', 'name', 'email', 'phone'])
                            ->missing('address')
                            ->missing('deleted_at')
                    )
                    ->has(
                        'products.0',
                        fn (Assert $product) => $product
                            ->hasAll([
                                'id',
                                'name',
                                'sku',
                                'category',
                                'default_selling_price',
                                'default_production_cost',
                            ])
                            ->has(
                                'category',
                                fn (Assert $category) => $category
                                    ->hasAll(['id', 'name'])
                            )
                            ->missing('description')
                            ->missing('is_active')
                            ->missing('deleted_at')
                    )
            );
    }

    public function test_customer_and_product_lookups_are_bounded(): void
    {
        $category = ProductCategory::factory()->create();
        Customer::factory()->count(251)->create();
        Product::factory()
            ->count(251)
            ->for($category, 'category')
            ->create();

        $this->actingAs($this->user())
            ->get(route('transactions.create'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('customers', 250)
                    ->has('products', 250)
            );
    }

    public function test_valid_multi_item_store_uses_existing_action_and_redirects_to_detail(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $defaultPriceProduct = Product::factory()->create([
            'name' => 'Default Price Product',
            'default_selling_price' => '100.00',
            'default_production_cost' => '40.00',
        ]);
        $overrideProduct = Product::factory()->create([
            'name' => 'Override Product',
            'default_selling_price' => '200.00',
            'default_production_cost' => '75.00',
        ]);

        $response = $this->actingAs($user)
            ->post(route('transactions.store'), [
                'customer_id' => (string) $customer->id,
                'transaction_date' => '2026-07-23',
                'items' => [
                    [
                        'product_id' => (string) $defaultPriceProduct->id,
                        'quantity' => '2',
                        'unit_selling_price' => null,
                    ],
                    [
                        'product_id' => (string) $overrideProduct->id,
                        'quantity' => '3',
                        'unit_selling_price' => '150.50',
                    ],
                ],
            ]);
        $transaction = Transaction::query()->sole();
        $items = $transaction->items()->orderBy('id')->get();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('transactions.show', $transaction)
            );
        $this->assertSame($user->id, $transaction->created_by_user_id);
        $this->assertSame($customer->id, $transaction->customer_id);
        $this->assertSame('100.00', $items[0]->unit_selling_price);
        $this->assertSame('40.00', $items[0]->unit_production_cost);
        $this->assertSame('150.50', $items[1]->unit_selling_price);
        $this->assertSame('75.00', $items[1]->unit_production_cost);
        $this->assertSame('651.50', $transaction->total_revenue);
        $this->assertSame('305.00', $transaction->total_production_cost);
        $this->assertSame('346.50', $transaction->total_net_profit);
    }

    public function test_duplicate_product_rows_are_accepted(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->post(
                route('transactions.store'),
                $this->payload($customer, items: [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'unit_selling_price' => '10.00',
                    ],
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                        'unit_selling_price' => '20.00',
                    ],
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('transaction_items', 2);
    }

    public function test_form_validation_preserves_old_input(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->from(route('transactions.create'))
            ->post(route('transactions.store'), [
                'customer_id' => (string) $customer->id,
                'transaction_date' => '2026-07-23',
                'items' => [[
                    'product_id' => (string) $product->id,
                    'quantity' => '0',
                    'unit_selling_price' => '125.00',
                ]],
            ])
            ->assertRedirect(route('transactions.create'))
            ->assertSessionHasErrors('items.0.quantity')
            ->assertSessionHasInput('transaction_date', '2026-07-23')
            ->assertSessionHasInput(
                'items.0.unit_selling_price',
                '125.00'
            );
    }

    public function test_domain_error_is_mapped_to_validation_error(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $product = Product::factory()->inactive()->create();

        $this->actingAs($user)
            ->from(route('transactions.create'))
            ->post(
                route('transactions.store'),
                $this->payload($customer, $product)
            )
            ->assertRedirect(route('transactions.create'))
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);
    }

    #[DataProvider('protectedTopLevelFields')]
    public function test_protected_top_level_fields_are_rejected(
        string $field,
        mixed $value
    ): void {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $payload = $this->payload($customer);
        $payload[$field] = $value;

        $this->actingAs($user)
            ->post(route('transactions.store'), $payload)
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('transactions', 0);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function protectedTopLevelFields(): array
    {
        return [
            'transaction number' => [
                'transaction_number',
                'TRX-CLIENT',
            ],
            'status' => ['status', 'distributed'],
            'revenue' => ['total_revenue', '1.00'],
            'cost' => ['total_production_cost', '1.00'],
            'profit' => ['total_net_profit', '1.00'],
            'creator' => ['created_by_user_id', 999],
            'completion timestamp' => [
                'completed_at',
                '2026-07-23 00:00:00',
            ],
            'idempotency key' => [
                'idempotency_key',
                '00000000-0000-0000-0000-000000000000',
            ],
        ];
    }

    #[DataProvider('protectedItemFields')]
    public function test_protected_item_fields_are_rejected(
        string $field
    ): void {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $payload = $this->payload($customer);
        $payload['items'][0][$field] = '1.00';

        $this->actingAs($user)
            ->post(route('transactions.store'), $payload)
            ->assertSessionHasErrors("items.0.{$field}");

        $this->assertDatabaseCount('transactions', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function protectedItemFields(): array
    {
        return [
            'production cost' => ['unit_production_cost'],
            'product snapshot' => ['product_name_snapshot'],
            'sku snapshot' => ['product_sku_snapshot'],
            'category snapshot' => ['category_name_snapshot'],
            'revenue total' => ['total_revenue'],
            'cost total' => ['total_production_cost'],
            'profit total' => ['net_profit'],
            'rule version' => ['applied_profit_scheme_version_id'],
            'rule timestamp' => ['rule_resolved_at'],
        ];
    }

    public function test_failed_second_item_leaves_no_partial_state(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $validProduct = Product::factory()->create();

        $this->actingAs($user)
            ->post(
                route('transactions.store'),
                $this->payload($customer, items: [
                    [
                        'product_id' => $validProduct->id,
                        'quantity' => 1,
                    ],
                    [
                        'product_id' => 2_147_483_647,
                        'quantity' => 1,
                    ],
                ])
            )
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);
    }

    public function test_large_product_lookup_does_not_create_category_n_plus_one_queries(): void
    {
        $user = $this->user();
        $category = ProductCategory::factory()->create();
        Product::factory()
            ->count(50)
            ->for($category, 'category')
            ->create();
        $this->actingAs($user);
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->get(route('transactions.create'))->assertOk();
            $queryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        $this->assertLessThanOrEqual(8, $queryCount);
    }
}
