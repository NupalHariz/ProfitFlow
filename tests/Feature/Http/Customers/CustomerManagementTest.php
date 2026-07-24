<?php

namespace Tests\Feature\Http\Customers;

use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class CustomerManagementTest extends CustomerHttpTestCase
{
    public function test_verified_user_can_create_normalized_customer(): void
    {
        $response = $this->actingAs($this->user())
            ->post(route('customers.store'), [
                'name' => '  PT Nusantara Retail  ',
                'email' => '  finance@nusantara.test  ',
                'phone' => '  +62 21 555 0101  ',
                'address' => '  Jakarta  ',
            ]);
        $customer = Customer::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customers.show', $customer));
        $this->assertSame('PT Nusantara Retail', $customer->name);
        $this->assertSame(
            'finance@nusantara.test',
            $customer->email
        );
        $this->assertSame('+62215550101', $customer->phone);
        $this->assertSame('Jakarta', $customer->address);
    }

    public function test_customer_can_be_created_with_only_name_and_blank_options_become_null(): void
    {
        $this->actingAs($this->user())
            ->post(route('customers.store'), [
                'name' => 'Only Name',
                'email' => ' ',
                'phone' => '',
                'address' => "\n",
            ])
            ->assertSessionHasNoErrors();

        $customer = Customer::query()->sole();
        $this->assertSame('Only Name', $customer->name);
        $this->assertNull($customer->email);
        $this->assertNull($customer->phone);
        $this->assertNull($customer->address);
    }

    #[DataProvider('invalidNames')]
    public function test_name_validation_is_enforced(mixed $name): void
    {
        $this->actingAs($this->user())
            ->post(
                route('customers.store'),
                $this->customerPayload(['name' => $name])
            )
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('customers', 0);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidNames(): array
    {
        return [
            'missing' => [null],
            'blank' => ['   '],
            'too long' => [str_repeat('x', 161)],
        ];
    }

    public function test_email_is_optional_validated_and_not_unique(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(
                route('customers.store'),
                $this->customerPayload(['email' => 'invalid'])
            )
            ->assertSessionHasErrors('email');
        $this->actingAs($user)
            ->post(route('customers.store'), $this->customerPayload())
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('customers.store'), $this->customerPayload([
                'name' => 'Duplicate Email Customer',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            2,
            Customer::query()
                ->where('email', 'finance@nusantara.test')
                ->count()
        );
    }

    public function test_phone_length_is_enforced_and_address_is_optional(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('customers.store'), $this->customerPayload([
                'phone' => str_repeat('1', 41),
            ]))
            ->assertSessionHasErrors('phone');
        $this->actingAs($user)
            ->post(route('customers.store'), $this->customerPayload([
                'address' => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Customer::query()->sole()->address);
    }

    public function test_store_rejects_protected_fields(): void
    {
        $this->actingAs($this->user())
            ->post(route('customers.store'), [
                ...$this->customerPayload(),
                'id' => 999,
                'deleted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
                'transactions' => [['id' => 1]],
                'transactions_count' => 999,
            ])
            ->assertSessionHasErrors([
                'id',
                'deleted_at',
                'created_at',
                'updated_at',
                'transactions',
                'transactions_count',
            ]);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_verified_user_can_update_customer(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->user())
            ->patch(route('customers.update', $customer), [
                'name' => '  Updated Name  ',
                'email' => ' updated@example.com ',
                'phone' => '0812 3456-7890',
                'address' => ' Bandung ',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customers.show', $customer));

        $customer->refresh();
        $this->assertSame('Updated Name', $customer->name);
        $this->assertSame('updated@example.com', $customer->email);
        $this->assertSame('+6281234567890', $customer->phone);
        $this->assertSame('Bandung', $customer->address);
    }

    public function test_update_does_not_modify_transaction_rows_or_items(): void
    {
        $customer = $this->customer();
        $transaction = Transaction::factory()
            ->for($customer)
            ->distributed()
            ->create([
                'total_revenue' => '200.00',
                'total_production_cost' => '120.00',
                'total_net_profit' => '80.00',
            ]);
        $item = TransactionItem::factory()
            ->for($transaction)
            ->create();
        $transactionBefore = DB::table('transactions')
            ->where('id', $transaction->id)
            ->first();
        $itemBefore = DB::table('transaction_items')
            ->where('id', $item->id)
            ->first();

        $this->actingAs($this->user())
            ->patch(
                route('customers.update', $customer),
                $this->customerPayload(['name' => 'Current Name'])
            )
            ->assertSessionHasNoErrors();

        $this->assertEquals(
            $transactionBefore,
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->first()
        );
        $this->assertEquals(
            $itemBefore,
            DB::table('transaction_items')
                ->where('id', $item->id)
                ->first()
        );
    }

    public function test_detail_contains_customer_fields_counts_and_bounded_recent_summaries(): void
    {
        $customer = $this->customer([
            'name' => 'Detail Customer',
            'email' => 'detail@example.test',
            'phone' => '+6281200001001',
            'address' => 'Detailed Address',
        ]);
        $drafts = Transaction::factory()
            ->count(10)
            ->for($customer)
            ->create([
                'transaction_date' => '2026-07-22',
                'status' => TransactionStatus::Draft,
                'total_revenue' => '150.00',
                'total_net_profit' => '50.00',
            ]);
        $distributed = Transaction::factory()
            ->for($customer)
            ->distributed()
            ->create([
                'transaction_date' => '2026-07-23',
                'total_revenue' => '300.00',
                'total_production_cost' => '180.00',
                'total_net_profit' => '120.00',
            ]);
        $oldestDraft = $drafts->firstOrFail();

        $this->actingAs($this->user())
            ->get(route('customers.show', $customer))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('customers/show')
                    ->has(
                        'customer',
                        fn (Assert $detail) => $detail
                            ->hasAll([
                                'id',
                                'name',
                                'email',
                                'phone',
                                'address',
                                'created_at',
                                'updated_at',
                            ])
                            ->where('id', $customer->id)
                            ->where('name', 'Detail Customer')
                            ->missing('transactions')
                            ->missing('deleted_at')
                    )
                    ->where('transaction_summary.total_count', 11)
                    ->where('transaction_summary.draft_count', 10)
                    ->where(
                        'transaction_summary.distributed_count',
                        1
                    )
                    ->has('recent_transactions', 10)
                    ->where(
                        'recent_transactions.0.id',
                        $distributed->id
                    )
                    ->where(
                        'recent_transactions.0.total_revenue',
                        '300.00'
                    )
                    ->where(
                        'recent_transactions.0.total_net_profit',
                        '120.00'
                    )
                    ->where(
                        'recent_transactions',
                        fn (mixed $rows): bool => ! collect($rows)
                            ->pluck('id')
                            ->contains($oldestDraft->id)
                    )
                    ->has(
                        'recent_transactions.0',
                        fn (Assert $row) => $row
                            ->hasAll([
                                'id',
                                'transaction_number',
                                'transaction_date',
                                'status',
                                'total_revenue',
                                'total_net_profit',
                            ])
                            ->missing('items')
                            ->missing('distributions')
                            ->missing('customer')
                    )
            );
    }

    public function test_recent_transaction_tie_order_is_id_descending(): void
    {
        $customer = $this->customer();
        $first = Transaction::factory()
            ->for($customer)
            ->create(['transaction_date' => '2026-07-23']);
        $second = Transaction::factory()
            ->for($customer)
            ->create(['transaction_date' => '2026-07-23']);

        $this->actingAs($this->user())
            ->get(route('customers.show', $customer))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('recent_transactions.0.id', $second->id)
                    ->where('recent_transactions.1.id', $first->id)
            );
    }

    public function test_detail_query_count_is_bounded(): void
    {
        $customer = $this->customer();
        Transaction::factory()
            ->count(20)
            ->for($customer)
            ->create();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->user())
            ->get(route('customers.show', $customer))
            ->assertOk();
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(7, $queryCount);
    }

    public function test_customer_can_be_soft_deleted_with_referencing_transaction(): void
    {
        $customer = $this->customer();
        $transaction = Transaction::factory()
            ->for($customer)
            ->create([
                'total_revenue' => '100.00',
                'total_production_cost' => '60.00',
                'total_net_profit' => '40.00',
            ]);
        $before = Arr::only(
            $transaction->getRawOriginal(),
            [
                'customer_id',
                'status',
                'total_revenue',
                'total_production_cost',
                'total_net_profit',
            ]
        );

        $this->actingAs($this->user())
            ->delete(route('customers.destroy', $customer))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertSame(
            $before,
            Arr::only(
                Transaction::query()
                    ->findOrFail($transaction->id)
                    ->getRawOriginal(),
                array_keys($before)
            )
        );
    }

    public function test_customer_without_transactions_can_be_soft_deleted(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->user())
            ->delete(route('customers.destroy', $customer))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }
}
