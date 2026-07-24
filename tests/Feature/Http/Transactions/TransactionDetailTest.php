<?php

namespace Tests\Feature\Http\Transactions;

use App\Actions\Transactions\CancelTransaction;
use App\Actions\Transactions\CreateTransaction;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class TransactionDetailTest extends TransactionHttpTestCase
{
    public function test_detail_renders_stored_snapshots_and_exact_totals(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $category = $this->createCategory([
            'name' => 'Snapshot Category',
        ]);
        $product = $this->createProduct($category, [
            'name' => 'Snapshot Product',
            'sku' => 'SNAPSHOT-SKU',
            'default_selling_price' => '150.00',
            'default_production_cost' => '90.00',
        ]);
        $transaction = $this->draft($user, $customer, $product);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('transactions/show')
                    ->where(
                        'transaction.transaction_number',
                        $transaction->transaction_number
                    )
                    ->where(
                        'transaction.total_revenue',
                        '300.00'
                    )
                    ->where(
                        'transaction.total_production_cost',
                        '180.00'
                    )
                    ->where(
                        'transaction.total_net_profit',
                        '120.00'
                    )
                    ->where(
                        'transaction.items.0.product_name_snapshot',
                        'Snapshot Product'
                    )
                    ->where(
                        'transaction.items.0.product_sku_snapshot',
                        'SNAPSHOT-SKU'
                    )
                    ->where(
                        'transaction.items.0.category_name_snapshot',
                        'Snapshot Category'
                    )
                    ->where(
                        'transaction.items.0.unit_selling_price',
                        '150.00'
                    )
                    ->where(
                        'transaction.items.0.unit_production_cost',
                        '90.00'
                    )
            );
    }

    public function test_detail_keeps_using_snapshots_after_product_master_changes(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $category = $this->createCategory([
            'name' => 'Original Category',
        ]);
        $product = $this->createProduct($category, [
            'name' => 'Original Product',
            'sku' => 'ORIGINAL-SKU',
            'default_selling_price' => '100.00',
            'default_production_cost' => '60.00',
        ]);
        $transaction = $this->draft($user, $customer, $product);
        $product->update([
            'name' => 'Changed Product',
            'sku' => 'CHANGED-SKU',
            'default_selling_price' => '999.00',
            'default_production_cost' => '888.00',
        ]);
        $category->update(['name' => 'Changed Category']);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'transaction.items.0.product_name_snapshot',
                        'Original Product'
                    )
                    ->where(
                        'transaction.items.0.product_sku_snapshot',
                        'ORIGINAL-SKU'
                    )
                    ->where(
                        'transaction.items.0.category_name_snapshot',
                        'Original Category'
                    )
                    ->where(
                        'transaction.items.0.unit_selling_price',
                        '100.00'
                    )
                    ->where(
                        'transaction.items.0.unit_production_cost',
                        '60.00'
                    )
            );
    }

    public function test_distributed_detail_displays_soft_deleted_customer_with_marker(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create([
            'name' => 'Retired Historical Customer',
        ]);
        $transaction = $this->draft($user, $customer);
        $transaction->update([
            'status' => TransactionStatus::Distributed,
            'completed_by_user_id' => $user->id,
            'completed_at' => now()->subMinutes(2),
            'distribution_started_at' => now()->subMinute(),
            'distributed_at' => now(),
        ]);
        $customer->delete();

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'transaction.customer.name',
                        'Retired Historical Customer'
                    )
                    ->where(
                        'transaction.customer.is_deleted',
                        true
                    )
                    ->where(
                        'transaction.status',
                        TransactionStatus::Distributed->value
                    )
            );
    }

    public function test_draft_detail_is_editable_and_non_draft_detail_is_read_only(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $draft = $this->draft($user, $customer);
        $distributed = $this->draft($user, $customer);
        $distributed->update([
            'status' => TransactionStatus::Distributed,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.show', $draft))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('transaction.can_edit', true)
                    ->where('transaction.status', 'draft')
            );

        $this->actingAs($user)
            ->get(route('transactions.show', $distributed))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('transaction.can_edit', false)
                    ->where('transaction.status', 'distributed')
                    ->missing('transaction.edit_url')
            );
    }

    #[DataProvider('detailActionStates')]
    public function test_cancel_action_is_exposed_only_for_draft(
        TransactionStatus $status,
        bool $canCancel
    ): void {
        $user = $this->user();
        $transaction = $this->createTransaction(
            creator: $user,
            attributes: [
                'status' => $status,
                'cancelled_at' => $status === TransactionStatus::Cancelled
                    ? now()
                    : null,
            ]
        );
        $this->createTransactionItem($transaction);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('transaction.status', $status->value)
                    ->where('transaction.can_cancel', $canCancel)
                    ->where(
                        'transaction.can_edit',
                        $status === TransactionStatus::Draft
                    )
                    ->where(
                        'transaction.can_complete',
                        $status === TransactionStatus::Draft
                    )
            );
    }

    /**
     * @return array<string, array{TransactionStatus, bool}>
     */
    public static function detailActionStates(): array
    {
        return [
            'draft' => [TransactionStatus::Draft, true],
            'pending distribution' => [
                TransactionStatus::PendingDistribution,
                false,
            ],
            'processing distribution' => [
                TransactionStatus::ProcessingDistribution,
                false,
            ],
            'distributed' => [TransactionStatus::Distributed, false],
            'distribution failed' => [
                TransactionStatus::DistributionFailed,
                false,
            ],
            'cancelled' => [TransactionStatus::Cancelled, false],
        ];
    }

    public function test_cancelled_detail_keeps_customer_items_snapshots_totals_and_timestamp(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create([
            'name' => 'Cancelled Transaction Customer',
        ]);
        $category = $this->createCategory([
            'name' => 'Cancelled Snapshot Category',
        ]);
        $product = $this->createProduct($category, [
            'name' => 'Cancelled Snapshot Product',
            'sku' => 'CANCELLED-SNAPSHOT',
            'default_selling_price' => '150.00',
            'default_production_cost' => '90.00',
        ]);
        $transaction = $this->draft(
            $user,
            $customer,
            $product
        );
        app(CancelTransaction::class)->execute($transaction);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('transaction.status', 'cancelled')
                    ->where('transaction.can_edit', false)
                    ->where('transaction.can_complete', false)
                    ->where('transaction.can_cancel', false)
                    ->where(
                        'transaction.can_retry_distribution',
                        false
                    )
                    ->where(
                        'transaction.can_redrive_distribution',
                        false
                    )
                    ->where(
                        'transaction.customer.name',
                        'Cancelled Transaction Customer'
                    )
                    ->where('transaction.total_revenue', '300.00')
                    ->where(
                        'transaction.total_production_cost',
                        '180.00'
                    )
                    ->where('transaction.total_net_profit', '120.00')
                    ->where(
                        'transaction.cancelled_at',
                        fn (mixed $value): bool => is_string($value)
                            && $value !== ''
                    )
                    ->has('transaction.items', 1)
                    ->where(
                        'transaction.items.0.product_name_snapshot',
                        'Cancelled Snapshot Product'
                    )
                    ->where(
                        'transaction.items.0.product_sku_snapshot',
                        'CANCELLED-SNAPSHOT'
                    )
                    ->where(
                        'transaction.items.0.category_name_snapshot',
                        'Cancelled Snapshot Category'
                    )
                    ->where(
                        'transaction.items.0.applied_rule',
                        null
                    )
                    ->where(
                        'transaction.items.0.rule_resolved_at',
                        null
                    )
                    ->has('transaction.items.0.distributions', 0)
            );
    }

    public function test_detail_sends_no_fabricated_rule_or_distribution_graphs(): void
    {
        $user = $this->user();
        $transaction = $this->draft(
            $user,
            Customer::factory()->create()
        );

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->missing('transaction.distributions')
                    ->missing('transaction.profit_scheme_versions')
                    ->where(
                        'transaction.items.0.applied_profit_scheme_version_id',
                        null
                    )
                    ->where(
                        'transaction.items.0.rule_resolved_at',
                        null
                    )
            );
    }

    public function test_detail_query_count_does_not_grow_with_items(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $items = array_map(
            fn (int $index): array => [
                'product_id' => $product->id,
                'quantity' => $index,
                'unit_selling_price' => null,
            ],
            range(1, 20)
        );
        $transaction = app(CreateTransaction::class)->execute(
            $this->payload($customer, items: $items),
            $user
        );
        $this->actingAs($user);
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->get(route('transactions.show', $transaction))
                ->assertOk();
            $queryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame(20, TransactionItem::query()
            ->where('transaction_id', $transaction->id)
            ->count());
        $this->assertLessThanOrEqual(8, $queryCount);
    }
}
