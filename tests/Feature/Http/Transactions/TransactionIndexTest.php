<?php

namespace Tests\Feature\Http\Transactions;

use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class TransactionIndexTest extends TransactionHttpTestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('transactions.index'))
            ->assertRedirect(route('login'));
    }

    public function test_verified_user_can_access_index(): void
    {
        $this->actingAs($this->user())
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('transactions/index')
                    ->has('transactions.data')
            );
    }

    public function test_index_is_paginated(): void
    {
        $user = $this->user();
        Transaction::factory()
            ->count(11)
            ->for($user, 'creator')
            ->create();

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('transactions.data', 10)
                    ->where('transactions.meta.current_page', 1)
                    ->where('transactions.meta.last_page', 2)
                    ->where('transactions.meta.total', 11)
            );
    }

    public function test_search_matches_transaction_number(): void
    {
        $user = $this->user();
        $this->transaction($user, [
            'transaction_number' => 'TRX-SEARCH-TARGET',
        ]);
        $this->transaction($user, [
            'transaction_number' => 'TRX-OTHER',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'search' => 'search-target',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('transactions.data', 1)
                    ->where(
                        'transactions.data.0.transaction_number',
                        'TRX-SEARCH-TARGET'
                    )
            );
    }

    public function test_search_matches_customer_name(): void
    {
        $user = $this->user();
        $target = Customer::factory()->create([
            'name' => 'Nusantara Retail',
        ]);
        $other = Customer::factory()->create([
            'name' => 'Other Customer',
        ]);
        $this->transaction($user, customer: $target);
        $this->transaction($user, customer: $other);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'search' => 'nusantara',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('transactions.data', 1)
                    ->where(
                        'transactions.data.0.customer.name',
                        'Nusantara Retail'
                    )
            );
    }

    public function test_index_displays_soft_deleted_customer_with_marker(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create([
            'name' => 'Historical Customer',
        ]);
        $transaction = $this->transaction($user, customer: $customer);
        $customer->delete();

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('transactions.data.0.id', $transaction->id)
                    ->where(
                        'transactions.data.0.customer.name',
                        'Historical Customer'
                    )
                    ->where(
                        'transactions.data.0.customer.is_deleted',
                        true
                    )
            );
    }

    public function test_status_filter_uses_transaction_status(): void
    {
        $user = $this->user();
        $this->transaction($user, [
            'status' => TransactionStatus::Draft,
        ]);
        $this->transaction($user, [
            'status' => TransactionStatus::Distributed,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'status' => TransactionStatus::Distributed->value,
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('transactions.data', 1)
                    ->where(
                        'transactions.data.0.status',
                        TransactionStatus::Distributed->value
                    )
            );
    }

    public function test_date_range_and_customer_filters_are_applied(): void
    {
        $user = $this->user();
        $target = Customer::factory()->create();
        $other = Customer::factory()->create();
        $this->transaction($user, [
            'transaction_date' => '2026-07-10',
        ], $target);
        $this->transaction($user, [
            'transaction_date' => '2026-07-20',
        ], $target);
        $this->transaction($user, [
            'transaction_date' => '2026-07-15',
        ], $other);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'date_from' => '2026-07-05',
                'date_until' => '2026-07-15',
                'customer_id' => $target->id,
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('transactions.data', 1)
                    ->where(
                        'transactions.data.0.transaction_date',
                        '2026-07-10'
                    )
                    ->where(
                        'transactions.data.0.customer.id',
                        $target->id
                    )
            );
    }

    public function test_index_ordering_is_date_then_id_descending(): void
    {
        $user = $this->user();
        $old = $this->transaction($user, [
            'transaction_date' => '2026-07-20',
        ]);
        $sameDateFirst = $this->transaction($user, [
            'transaction_date' => '2026-07-23',
        ]);
        $sameDateSecond = $this->transaction($user, [
            'transaction_date' => '2026-07-23',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('transactions.data.0.id', $sameDateSecond->id)
                    ->where('transactions.data.1.id', $sameDateFirst->id)
                    ->where('transactions.data.2.id', $old->id)
            );
    }

    public function test_index_props_are_summary_only(): void
    {
        $user = $this->user();
        $transaction = $this->transaction($user);
        TransactionItem::factory()
            ->count(2)
            ->for($transaction)
            ->create();

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has(
                        'transactions.data.0',
                        fn (Assert $row) => $row
                            ->hasAll([
                                'id',
                                'transaction_number',
                                'transaction_date',
                                'status',
                                'customer',
                                'total_revenue',
                                'total_production_cost',
                                'total_net_profit',
                                'item_count',
                                'created_at',
                            ])
                            ->where('item_count', 2)
                            ->missing('items')
                            ->missing('products')
                            ->missing('distributions')
                    )
            );
    }

    public function test_index_query_count_does_not_grow_with_rows(): void
    {
        $user = $this->user();

        foreach (range(1, 10) as $index) {
            $transaction = $this->transaction($user, [
                'transaction_number' => "TRX-QUERY-{$index}",
            ]);
            TransactionItem::factory()
                ->count(2)
                ->for($transaction)
                ->create();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->actingAs($user)
                ->get(route('transactions.index'))
                ->assertOk();
            $queryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        $this->assertLessThanOrEqual(8, $queryCount);
    }

    public function test_pagination_links_preserve_filters(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();

        foreach (range(1, 11) as $index) {
            $this->transaction($user, [
                'transaction_number' => "TRX-PRESERVE-{$index}",
                'transaction_date' => '2026-07-23',
                'status' => TransactionStatus::Draft,
            ], $customer);
        }

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'search' => 'PRESERVE',
                'status' => 'draft',
                'date_from' => '2026-07-01',
                'date_until' => '2026-07-31',
                'customer_id' => $customer->id,
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'transactions.links.next',
                        function (mixed $url) use ($customer): bool {
                            if (! is_string($url)) {
                                return false;
                            }

                            return str_contains($url, 'page=2')
                                && str_contains($url, 'search=PRESERVE')
                                && str_contains($url, 'status=draft')
                                && str_contains(
                                    $url,
                                    "customer_id={$customer->id}"
                                );
                        }
                    )
            );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transaction(
        User $user,
        array $attributes = [],
        ?Customer $customer = null
    ): Transaction {
        return Transaction::factory()
            ->for($customer ?? Customer::factory())
            ->for($user, 'creator')
            ->create($attributes);
    }
}
