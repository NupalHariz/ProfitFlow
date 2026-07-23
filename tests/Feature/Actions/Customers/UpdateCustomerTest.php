<?php

namespace Tests\Feature\Actions\Customers;

use App\Actions\Customers\UpdateCustomer;
use App\Enums\TransactionStatus;
use App\Exceptions\Customers\InvalidCustomer;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class UpdateCustomerTest extends CustomerActionTestCase
{
    public function test_it_updates_only_normalized_customer_fields(): void
    {
        $customer = $this->createManagedCustomer();

        $updated = app(UpdateCustomer::class)->execute($customer, [
            'name' => '  Updated Customer  ',
            'email' => '  updated@example.com  ',
            'phone' => '   ',
            'address' => '  Bandung  ',
        ]);

        $this->assertSame('Updated Customer', $updated->name);
        $this->assertSame('updated@example.com', $updated->email);
        $this->assertNull($updated->phone);
        $this->assertSame('Bandung', $updated->address);
    }

    public function test_update_does_not_modify_referencing_transactions(): void
    {
        $customer = $this->createManagedCustomer();
        $transaction = Transaction::factory()
            ->for($customer)
            ->distributed()
            ->create([
                'total_revenue' => '500.00',
                'total_production_cost' => '300.00',
                'total_net_profit' => '200.00',
            ]);
        $before = DB::table('transactions')
            ->where('id', $transaction->id)
            ->first();

        app(UpdateCustomer::class)->execute(
            $customer,
            $this->customerData([
                'name' => 'Current Customer Name',
            ])
        );

        $this->assertEquals(
            $before,
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->first()
        );
        $this->assertSame(
            'Current Customer Name',
            $transaction->fresh()?->customer?->name
        );
        $this->assertSame(
            TransactionStatus::Distributed,
            $transaction->fresh()?->status
        );
    }

    public function test_update_locks_customer_row(): void
    {
        $customer = $this->createManagedCustomer();
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(UpdateCustomer::class)->execute(
            $customer,
            $this->customerData()
        );
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertTrue(collect($queries)->contains(
            fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "customers"'
            ) && str_contains(strtolower($query['query']), 'for update')
        ));
    }

    public function test_invalid_update_is_atomic(): void
    {
        $customer = $this->createManagedCustomer();
        $before = DB::table('customers')
            ->where('id', $customer->id)
            ->first();

        try {
            app(UpdateCustomer::class)->execute($customer, [
                ...$this->customerData(['name' => 'Changed']),
                'transactions_count' => 999,
            ]);
            $this->fail('Expected invalid update to fail.');
        } catch (InvalidCustomer) {
        }

        $this->assertEquals(
            $before,
            DB::table('customers')
                ->where('id', $customer->id)
                ->first()
        );
    }
}
