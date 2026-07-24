<?php

namespace Tests\Feature\Http\ProfitRecipients;

use App\Enums\ProfitRecipientType;
use App\Models\ProfitDistribution;
use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeAllocation;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class ProfitRecipientIndexTest extends ProfitRecipientHttpTestCase
{
    public function test_index_is_paginated_fifteen_per_page_in_stable_order(): void
    {
        ProfitRecipient::factory()
            ->count(16)
            ->sequence(
                fn ($sequence): array => [
                    'display_name' => sprintf(
                        'Recipient %02d',
                        16 - $sequence->index
                    ),
                ]
            )
            ->create();

        $this->actingAs($this->user())
            ->get(route('profit-recipients.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('recipients.data', 15)
                    ->where(
                        'recipients.data.0.display_name',
                        'Recipient 01'
                    )
                    ->where('recipients.meta.current_page', 1)
                    ->where('recipients.meta.last_page', 2)
                    ->where('recipients.meta.per_page', 15)
                    ->where('recipients.meta.total', 16)
            );
    }

    #[DataProvider('searchCases')]
    public function test_search_is_case_insensitive_across_recipient_and_investor_fields(
        string $field,
        string $search
    ): void {
        $investor = $this->investor([
            'name' => $field === 'investor'
                ? 'Nusantara Capital'
                : 'Unrelated Investor',
        ]);
        $target = $this->recipient($investor, [
            'code' => $field === 'code'
                ? 'CAPITAL-SHARE'
                : 'OTHER-CODE',
            'display_name' => $field === 'display_name'
                ? 'Nusantara Allocation'
                : 'Other Allocation',
        ]);
        $this->recipient(null, [
            'type' => ProfitRecipientType::Company,
            'code' => 'UNRELATED',
            'display_name' => 'Unrelated Company',
        ]);

        $this->actingAs($this->user())
            ->get(route('profit-recipients.index', [
                'search' => $search,
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('recipients.data', 1)
                    ->where('recipients.data.0.id', $target->id)
                    ->where('filters.search', $search)
            );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function searchCases(): array
    {
        return [
            'code' => ['code', 'cApItAl-ShArE'],
            'display name' => ['display_name', 'nUsAnTaRa AlLoC'],
            'Investor name' => ['investor', 'nUsAnTaRa CaPiTaL'],
        ];
    }

    public function test_type_and_status_filters_are_database_backed(): void
    {
        $activeInvestor = $this->recipient();
        $inactiveInvestor = $this->recipient(null, [
            'is_active' => false,
        ]);
        $activeCompany = $this->recipient(null, [
            'type' => ProfitRecipientType::Company,
            'is_active' => true,
        ]);
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('profit-recipients.index', [
                'type' => 'company',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('recipients.data', 1)
                    ->where('recipients.data.0.id', $activeCompany->id)
                    ->where('filters.type', 'company')
            );
        $this->actingAs($user)
            ->get(route('profit-recipients.index', [
                'type' => 'investor',
                'status' => 'active',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('recipients.data', 1)
                    ->where('recipients.data.0.id', $activeInvestor->id)
            );
        $this->actingAs($user)
            ->get(route('profit-recipients.index', [
                'status' => 'inactive',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('recipients.data', 1)
                    ->where('recipients.data.0.id', $inactiveInvestor->id)
            );
    }

    public function test_index_payload_has_selected_investor_and_bounded_usage_counts(): void
    {
        $investor = $this->investor([
            'name' => 'Linked Investor',
            'email' => 'linked@example.test',
            'phone' => '+6281200001001',
            'is_active' => false,
        ]);
        $recipient = $this->recipient($investor, [
            'code' => 'RECIPIENT-SUMMARY',
            'display_name' => 'Summary Identity',
        ]);
        ProfitSchemeAllocation::factory()
            ->count(2)
            ->for($recipient, 'recipient')
            ->create();
        ProfitDistribution::factory()
            ->count(3)
            ->for($recipient, 'recipient')
            ->create();

        $this->actingAs($this->user())
            ->get(route('profit-recipients.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has(
                        'recipients.data.0',
                        fn (Assert $row) => $row
                            ->hasAll([
                                'id',
                                'code',
                                'type',
                                'display_name',
                                'is_active',
                                'created_at',
                                'updated_at',
                                'investor',
                                'usage_summary',
                            ])
                            ->where('id', $recipient->id)
                            ->missing('investor_id')
                            ->missing('allocations')
                            ->missing('distributions')
                    )
                    ->has(
                        'recipients.data.0.investor',
                        fn (Assert $summary) => $summary
                            ->hasAll([
                                'id',
                                'name',
                                'email',
                                'phone',
                                'is_active',
                            ])
                            ->where('id', $investor->id)
                            ->where('is_active', false)
                            ->missing('deleted_at')
                            ->missing('profit_recipient')
                    )
                    ->where(
                        'recipients.data.0.usage_summary.allocation_count',
                        2
                    )
                    ->where(
                        'recipients.data.0.usage_summary.distribution_count',
                        3
                    )
            );
    }

    public function test_pagination_preserves_all_filters(): void
    {
        ProfitRecipient::factory()
            ->count(16)
            ->create([
                'display_name' => 'Office Recipient',
                'is_active' => false,
            ]);

        $this->actingAs($this->user())
            ->get(route('profit-recipients.index', [
                'search' => 'Office',
                'type' => 'investor',
                'status' => 'inactive',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page->where(
                    'recipients.links.next',
                    fn (mixed $url): bool => is_string($url)
                        && str_contains($url, 'search=Office')
                        && str_contains($url, 'type=investor')
                        && str_contains($url, 'status=inactive')
                        && str_contains($url, 'page=2')
                )
            );
    }

    public function test_index_query_count_does_not_grow_with_rows_or_usage(): void
    {
        $recipients = ProfitRecipient::factory()->count(30)->create();

        foreach ($recipients->take(10) as $recipient) {
            ProfitSchemeAllocation::factory()
                ->for($recipient, 'recipient')
                ->create();
            ProfitDistribution::factory()
                ->for($recipient, 'recipient')
                ->create();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->user())
            ->get(route('profit-recipients.index'))
            ->assertOk();
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(9, $queryCount);
    }

    public function test_invalid_index_filters_are_rejected(): void
    {
        $this->actingAs($this->user())
            ->get(route('profit-recipients.index', [
                'search' => str_repeat('x', 101),
                'type' => 'partner',
                'status' => 'deleted',
                'page' => 0,
            ]))
            ->assertSessionHasErrors([
                'search',
                'type',
                'status',
                'page',
            ]);
    }
}
