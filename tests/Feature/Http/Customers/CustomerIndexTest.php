<?php

namespace Tests\Feature\Http\Customers;

use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class CustomerIndexTest extends CustomerHttpTestCase
{
    public function test_index_is_paginated_fifteen_per_page_in_stable_order(): void
    {
        Customer::factory()
            ->count(16)
            ->sequence(
                fn ($sequence): array => [
                    'name' => sprintf(
                        'Customer %02d',
                        16 - $sequence->index
                    ),
                ]
            )
            ->create();

        $this->actingAs($this->user())
            ->get(route('customers.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('customers.data', 15)
                    ->where('customers.data.0.name', 'Customer 01')
                    ->where('customers.meta.current_page', 1)
                    ->where('customers.meta.last_page', 2)
                    ->where('customers.meta.per_page', 15)
                    ->where('customers.meta.total', 16)
            );
    }

    #[DataProvider('searchCases')]
    public function test_search_is_case_insensitive_across_customer_contact_fields(
        string $field,
        string $stored,
        string $search
    ): void {
        $target = $this->customer([
            'name' => 'Target Customer',
            'email' => 'target@example.test',
            'phone' => '+6281234567890',
            $field => $stored,
        ]);
        $this->customer([
            'name' => 'Unrelated',
            'email' => 'other@example.test',
            'phone' => '+1 555 0100',
        ]);

        $this->actingAs($this->user())
            ->get(route('customers.index', ['search' => $search]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('customers.data', 1)
                    ->where('customers.data.0.id', $target->id)
                    ->where('filters.search', $search)
            );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function searchCases(): array
    {
        return [
            'name' => [
                'name',
                'Nusantara Wholesale',
                'nUsAnTaRa',
            ],
            'email' => [
                'email',
                'Accounts@Example.test',
                'aCcOuNtS@',
            ],
            'phone' => [
                'phone',
                '+6287799887766',
                '9988',
            ],
            'local phone prefix' => [
                'phone',
                '+6287799887766',
                '0877',
            ],
            'country phone prefix' => [
                'phone',
                '+6287799887766',
                '62877',
            ],
            'international phone prefix' => [
                'phone',
                '+6287799887766',
                '+62877',
            ],
        ];
    }

    public function test_soft_deleted_customers_are_excluded(): void
    {
        $visible = $this->customer(['name' => 'Visible']);
        $deleted = $this->customer(['name' => 'Deleted']);
        $deleted->delete();

        $this->actingAs($this->user())
            ->get(route('customers.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('customers.data', 1)
                    ->where('customers.data.0.id', $visible->id)
            );
    }

    public function test_index_payload_is_explicit_and_includes_transaction_count_only(): void
    {
        $customer = $this->customer();
        Transaction::factory()
            ->count(2)
            ->for($customer)
            ->create();

        $this->actingAs($this->user())
            ->get(route('customers.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has(
                        'customers.data.0',
                        fn (Assert $row) => $row
                            ->hasAll([
                                'id',
                                'name',
                                'email',
                                'phone',
                                'address',
                                'transactions_count',
                                'created_at',
                                'updated_at',
                            ])
                            ->where('transactions_count', 2)
                            ->missing('transactions')
                            ->missing('deleted_at')
                    )
            );
    }

    public function test_pagination_links_preserve_search(): void
    {
        Customer::factory()
            ->count(16)
            ->create(['name' => 'Office Customer']);

        $this->actingAs($this->user())
            ->get(route('customers.index', ['search' => 'Office']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'customers.links.next',
                        fn (mixed $url): bool => is_string($url)
                            && str_contains($url, 'search=Office')
                            && str_contains($url, 'page=2')
                    )
            );
    }

    public function test_index_query_count_does_not_grow_with_customer_rows(): void
    {
        $customers = Customer::factory()->count(30)->create();

        foreach ($customers->take(10) as $customer) {
            Transaction::factory()
                ->count(2)
                ->for($customer)
                ->create();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->user())
            ->get(route('customers.index'))
            ->assertOk();
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(7, $queryCount);
    }

    public function test_invalid_index_query_is_rejected(): void
    {
        $this->actingAs($this->user())
            ->get(route('customers.index', [
                'search' => str_repeat('x', 101),
                'page' => 0,
            ]))
            ->assertSessionHasErrors(['search', 'page']);
    }
}
