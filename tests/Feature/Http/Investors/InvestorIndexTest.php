<?php

namespace Tests\Feature\Http\Investors;

use App\Models\Investor;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class InvestorIndexTest extends InvestorHttpTestCase
{
    public function test_index_is_paginated_fifteen_per_page_in_stable_order(): void
    {
        Investor::factory()
            ->count(16)
            ->sequence(
                fn ($sequence): array => [
                    'name' => sprintf(
                        'Investor %02d',
                        16 - $sequence->index
                    ),
                ]
            )
            ->create();

        $this->actingAs($this->user())
            ->get(route('investors.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('investors.data', 15)
                    ->where('investors.data.0.name', 'Investor 01')
                    ->where('investors.meta.current_page', 1)
                    ->where('investors.meta.last_page', 2)
                    ->where('investors.meta.per_page', 15)
                    ->where('investors.meta.total', 16)
            );
    }

    #[DataProvider('searchCases')]
    public function test_search_is_case_insensitive_across_contact_fields(
        string $field,
        string $stored,
        string $search
    ): void {
        $target = $this->investor([
            'name' => 'Target Investor',
            'email' => 'target@example.test',
            'phone' => '+6281234567890',
            $field => $stored,
        ]);
        $this->investor([
            'name' => 'Unrelated',
            'email' => 'other@example.test',
            'phone' => '+1 555 0100',
        ]);

        $this->actingAs($this->user())
            ->get(route('investors.index', ['search' => $search]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('investors.data', 1)
                    ->where('investors.data.0.id', $target->id)
                    ->where('filters.search', $search)
            );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function searchCases(): array
    {
        return [
            'name' => ['name', 'Nusantara Capital', 'nUsAnTaRa'],
            'email' => [
                'email',
                'Capital@Example.test',
                'cApItAl@',
            ],
            'phone' => ['phone', '+6287799887766', '9988'],
            'local phone prefix' => ['phone', '+6287799887766', '0877'],
            'country phone prefix' => ['phone', '+6287799887766', '62877'],
            'international phone prefix' => [
                'phone',
                '+6287799887766',
                '+62877',
            ],
        ];
    }

    public function test_status_filters_and_soft_delete_are_database_backed(): void
    {
        $active = $this->investor(['name' => 'Active']);
        $inactive = $this->investor([
            'name' => 'Inactive',
            'is_active' => false,
        ]);
        $deleted = $this->investor(['name' => 'Deleted']);
        $deleted->delete();
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('investors.index', ['status' => 'active']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('investors.data', 1)
                    ->where('investors.data.0.id', $active->id)
                    ->where('filters.status', 'active')
            );
        $this->actingAs($user)
            ->get(route('investors.index', ['status' => 'inactive']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('investors.data', 1)
                    ->where('investors.data.0.id', $inactive->id)
                    ->where('filters.status', 'inactive')
            );
    }

    public function test_index_payload_is_explicit_with_selected_recipient_summary(): void
    {
        $investor = $this->investor();
        $recipient = $this->recipient($investor, [
            'code' => 'INVESTOR-SUMMARY',
            'display_name' => 'Allocation Identity',
            'is_active' => false,
        ]);

        $this->actingAs($this->user())
            ->get(route('investors.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has(
                        'investors.data.0',
                        fn (Assert $row) => $row
                            ->hasAll([
                                'id',
                                'name',
                                'email',
                                'phone',
                                'is_active',
                                'created_at',
                                'updated_at',
                                'linked_recipient',
                            ])
                            ->where('id', $investor->id)
                            ->missing('deleted_at')
                            ->missing('profit_recipient')
                            ->missing('allocations')
                            ->missing('distributions')
                    )
                    ->has(
                        'investors.data.0.linked_recipient',
                        fn (Assert $linked) => $linked
                            ->hasAll([
                                'id',
                                'code',
                                'type',
                                'display_name',
                                'is_active',
                            ])
                            ->where('id', $recipient->id)
                            ->where('code', 'INVESTOR-SUMMARY')
                            ->where('display_name', 'Allocation Identity')
                            ->where('is_active', false)
                            ->missing('investor_id')
                            ->missing('allocations')
                            ->missing('distributions')
                    )
            );
    }

    public function test_pagination_links_preserve_search_and_status(): void
    {
        Investor::factory()
            ->count(16)
            ->create([
                'name' => 'Office Investor',
                'is_active' => false,
            ]);

        $this->actingAs($this->user())
            ->get(route('investors.index', [
                'search' => 'Office',
                'status' => 'inactive',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'investors.links.next',
                        fn (mixed $url): bool => is_string($url)
                            && str_contains($url, 'search=Office')
                            && str_contains($url, 'status=inactive')
                            && str_contains($url, 'page=2')
                    )
            );
    }

    public function test_index_query_count_does_not_grow_with_recipient_rows(): void
    {
        $investors = Investor::factory()->count(30)->create();

        foreach ($investors->take(10) as $investor) {
            $this->recipient($investor);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->user())
            ->get(route('investors.index'))
            ->assertOk();
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(8, $queryCount);
    }

    public function test_invalid_index_query_is_rejected(): void
    {
        $this->actingAs($this->user())
            ->get(route('investors.index', [
                'search' => str_repeat('x', 101),
                'status' => 'deleted',
                'page' => 0,
            ]))
            ->assertSessionHasErrors(['search', 'status', 'page']);
    }
}
