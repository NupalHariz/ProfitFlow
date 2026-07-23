<?php

namespace Tests\Feature\Actions\Customers;

use App\Actions\Customers\DeleteCustomer;
use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\ProfitDistribution;
use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class DeleteCustomerTest extends CustomerActionTestCase
{
    public function test_customer_without_transactions_is_soft_deleted(): void
    {
        $customer = $this->createManagedCustomer();

        app(DeleteCustomer::class)->execute($customer);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertNull(
            $customer->transactions()->first()
        );
    }

    public function test_customer_with_draft_transaction_is_soft_deleted_without_mutating_draft(): void
    {
        $customer = $this->createManagedCustomer();
        $transaction = Transaction::factory()
            ->for($customer)
            ->create([
                'status' => TransactionStatus::Draft,
                'total_revenue' => '100.00',
                'total_production_cost' => '60.00',
                'total_net_profit' => '40.00',
            ]);
        $before = DB::table('transactions')
            ->where('id', $transaction->id)
            ->first();

        app(DeleteCustomer::class)->execute($customer);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertEquals(
            $before,
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->first()
        );
        $this->assertSame(
            $customer->id,
            $transaction->fresh()?->customer_id
        );
        $this->assertSame(
            $customer->name,
            $transaction->fresh()?->customer?->name
        );
        $this->assertTrue(
            (bool) $transaction->fresh()?->customer?->trashed()
        );
    }

    public function test_distributed_history_remains_byte_for_byte_unchanged(): void
    {
        Queue::fake();
        $customer = $this->createManagedCustomer();
        $product = Product::factory()->create();
        $version = ProfitSchemeVersion::factory()
            ->forCategory($product->category)
            ->published()
            ->create();
        $transaction = Transaction::factory()
            ->for($customer)
            ->distributed()
            ->create([
                'total_revenue' => '300.00',
                'total_production_cost' => '180.00',
                'total_net_profit' => '120.00',
            ]);
        $item = TransactionItem::factory()
            ->for($transaction)
            ->for($product)
            ->for($version, 'appliedProfitSchemeVersion')
            ->create([
                'quantity' => 2,
                'unit_selling_price' => '150.00',
                'unit_production_cost' => '90.00',
                'rule_resolved_at' => now(),
            ]);
        $recipient = ProfitRecipient::factory()->company()->create();
        $distribution = ProfitDistribution::factory()
            ->for($transaction)
            ->for($item, 'transactionItem')
            ->for($version, 'profitSchemeVersion')
            ->for($recipient, 'recipient')
            ->create([
                'recipient_name_snapshot' => 'Company Snapshot',
                'percentage' => '100.0000',
                'amount' => '120.00',
            ]);
        $transactionState = $this->databaseRow(
            'transactions',
            $transaction->id
        );
        $itemState = $this->databaseRow(
            'transaction_items',
            $item->id
        );
        $versionState = $this->databaseRow(
            'profit_scheme_versions',
            $version->id
        );
        $distributionState = $this->databaseRow(
            'profit_distributions',
            $distribution->id
        );

        app(DeleteCustomer::class)->execute($customer);

        $this->assertEquals(
            $transactionState,
            $this->databaseRow('transactions', $transaction->id)
        );
        $this->assertEquals(
            $itemState,
            $this->databaseRow('transaction_items', $item->id)
        );
        $this->assertEquals(
            $versionState,
            $this->databaseRow('profit_scheme_versions', $version->id)
        );
        $this->assertEquals(
            $distributionState,
            $this->databaseRow(
                'profit_distributions',
                $distribution->id
            )
        );
        Queue::assertNothingPushed();
    }

    public function test_delete_locks_customer_row(): void
    {
        $customer = $this->createManagedCustomer();
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(DeleteCustomer::class)->execute($customer);
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertTrue(collect($queries)->contains(
            fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "customers"'
            ) && str_contains(strtolower($query['query']), 'for update')
        ));
    }

    private function databaseRow(string $table, int $id): object
    {
        $row = DB::table($table)->where('id', $id)->first();

        $this->assertNotNull($row);

        return $row;
    }
}
