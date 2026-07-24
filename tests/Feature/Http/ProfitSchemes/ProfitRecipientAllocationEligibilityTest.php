<?php

namespace Tests\Feature\Http\ProfitSchemes;

use App\Actions\ProfitRecipients\SetProfitRecipientActive;
use App\Models\Investor;
use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class ProfitRecipientAllocationEligibilityTest extends ProfitSchemeHttpTestCase
{
    public function test_newly_created_draft_uses_eligible_recipient_options_on_its_allocation_screen(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $company = ProfitRecipient::factory()->company()->create([
            'display_name' => 'Alpha Company',
            'is_active' => true,
        ]);
        $inactiveCompany = ProfitRecipient::factory()
            ->company()
            ->create([
                'display_name' => 'Hidden Company',
                'is_active' => false,
            ]);
        $inactiveInvestor = Investor::factory()->inactive()->create([
            'name' => 'Inactive Profile',
        ]);
        $investorRecipient = ProfitRecipient::factory()
            ->for($inactiveInvestor, 'investor')
            ->create([
                'display_name' => 'Beta Investor Recipient',
                'is_active' => true,
            ]);
        $deletedInvestor = Investor::factory()->create();
        $legacyRecipient = ProfitRecipient::factory()
            ->for($deletedInvestor, 'investor')
            ->create(['is_active' => true]);
        $deletedInvestor->delete();

        $this->actingAs($user)
            ->post(
                route('profit-schemes.versions.store', $scheme),
                $this->versionPayload()
            )
            ->assertSessionHasNoErrors();
        $version = ProfitSchemeVersion::query()
            ->where('profit_scheme_id', $scheme->id)
            ->sole();

        $this->actingAs($user)
            ->get(route('profit-schemes.versions.edit', [
                'scheme' => $scheme,
                'version' => $version,
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('profit-schemes/versions/edit')
                    ->where(
                        'recipients',
                        fn (mixed $recipients): bool => $recipients
                            instanceof Collection
                            && $recipients->pluck('id')->all() === [
                                $company->id,
                                $investorRecipient->id,
                            ]
                            && ! $recipients->pluck('id')->contains(
                                $inactiveCompany->id
                            )
                            && ! $recipients->pluck('id')->contains(
                                $legacyRecipient->id
                            )
                    )
                    ->where(
                        'recipients.1.investor.id',
                        $inactiveInvestor->id
                    )
                    ->where('recipients.1.investor.is_active', false)
                    ->where('recipients.1.is_available', true)
                    ->has('allocation_recipients', 0)
            );
    }

    public function test_edit_get_preserves_and_marks_an_unavailable_draft_allocation_without_exposing_it_as_an_option(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $recipient = $this->recipient();
        $this->allocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '37.5000',
        ]]);
        app(SetProfitRecipientActive::class)->execute(
            $recipient,
            ['is_active' => false]
        );
        $versionBefore = (array) DB::table('profit_scheme_versions')
            ->where('id', $version->id)
            ->firstOrFail();
        $allocationBefore = (array) DB::table('profit_scheme_allocations')
            ->where('profit_scheme_version_id', $version->id)
            ->sole();

        $this->actingAs($user)
            ->get(route('profit-schemes.versions.edit', [
                'scheme' => $scheme,
                'version' => $version,
            ]))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('version.is_published', false)
                    ->where(
                        'version.allocations.0.profit_recipient_id',
                        $recipient->id
                    )
                    ->where(
                        'version.allocations.0.percentage',
                        '37.5000'
                    )
                    ->where(
                        'recipients',
                        fn (mixed $recipients): bool => $recipients
                            instanceof Collection
                            && ! $recipients->pluck('id')->contains(
                                $recipient->id
                            )
                    )
                    ->has(
                        'allocation_recipients.0',
                        fn (Assert $current) => $current
                            ->where('id', $recipient->id)
                            ->where('code', $recipient->code)
                            ->where(
                                'display_name',
                                $recipient->display_name
                            )
                            ->where('type', 'investor')
                            ->where('is_active', false)
                            ->where('is_available', false)
                            ->has(
                                'investor',
                                fn (Assert $investor) => $investor
                                    ->where(
                                        'id',
                                        $recipient->investor_id
                                    )
                                    ->hasAll([
                                        'name',
                                        'is_active',
                                    ])
                            )
                    )
            );

        $this->assertSame(
            $versionBefore,
            (array) DB::table('profit_scheme_versions')
                ->where('id', $version->id)
                ->firstOrFail()
        );
        $this->assertSame(
            $allocationBefore,
            (array) DB::table('profit_scheme_allocations')
                ->where('profit_scheme_version_id', $version->id)
                ->sole()
        );
    }

    public function test_unavailable_draft_recipient_requires_an_atomic_replacement(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user, ['priority' => 73]);
        $unavailable = $this->recipient();
        $replacement = ProfitRecipient::factory()->company()->create();
        $this->allocations($version, [[
            'profit_recipient_id' => $unavailable->id,
            'percentage' => '37.5000',
        ]]);
        app(SetProfitRecipientActive::class)->execute(
            $unavailable,
            ['is_active' => false]
        );
        $allocationBefore = (array) ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->sole()
            ->getAttributes();

        $this->actingAs($user)
            ->put(route('profit-schemes.versions.allocations.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'allocations' => [[
                    'profit_recipient_id' => $unavailable->id,
                    'percentage' => '37.5000',
                ]],
            ])
            ->assertSessionHasErrors([
                'allocations.0.profit_recipient_id' => 'The selected Profit Recipient is no longer available. Choose an active recipient and try again.',
            ]);

        $this->assertSame(
            $allocationBefore,
            ProfitSchemeAllocation::query()
                ->where('profit_scheme_version_id', $version->id)
                ->sole()
                ->getAttributes()
        );

        $this->actingAs($user)
            ->put(route('profit-schemes.versions.allocations.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'allocations' => [[
                    'profit_recipient_id' => $replacement->id,
                    'percentage' => '37.5000',
                ]],
            ])
            ->assertSessionHasNoErrors();

        $stored = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->sole();
        $this->assertSame($replacement->id, $stored->profit_recipient_id);
        $this->assertSame('37.5000', $stored->percentage);
        $this->assertSame(73, $version->fresh()?->priority);
        $this->assertNull($version->fresh()?->published_at);
    }

    public function test_reactivated_recipient_reappears_without_automatic_allocation_changes(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $recipient = $this->recipient(active: false);

        $this->actingAs($user)
            ->get(route('profit-schemes.versions.edit', [
                'scheme' => $scheme,
                'version' => $version,
            ]))
            ->assertInertia(
                fn (Assert $page) => $page->where(
                    'recipients',
                    fn (mixed $recipients): bool => $recipients
                        instanceof Collection
                        && ! $recipients->pluck('id')->contains(
                            $recipient->id
                        )
                )
            );
        app(SetProfitRecipientActive::class)->execute(
            $recipient,
            ['is_active' => true]
        );

        $this->actingAs($user)
            ->get(route('profit-schemes.versions.edit', [
                'scheme' => $scheme,
                'version' => $version,
            ]))
            ->assertInertia(
                fn (Assert $page) => $page->where(
                    'recipients',
                    fn (mixed $recipients): bool => $recipients
                        instanceof Collection
                        && $recipients->pluck('id')->contains(
                            $recipient->id
                        )
                )
            );
        $this->assertDatabaseMissing('profit_scheme_allocations', [
            'profit_scheme_version_id' => $version->id,
        ]);
        $this->assertNull($version->fresh()?->published_at);
    }

    public function test_recipient_options_are_bounded_selected_and_eager_loaded_without_n_plus_one_queries(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $investor = Investor::factory()->inactive()->create();
        ProfitRecipient::factory()
            ->for($investor, 'investor')
            ->create(['display_name' => '000 Investor Recipient']);
        ProfitRecipient::factory()->company()->count(250)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user)
            ->get(route('profit-schemes.versions.edit', [
                'scheme' => $scheme,
                'version' => $version,
            ]));
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $response->assertInertia(
            fn (Assert $page) => $page
                ->has('recipients', 250)
                ->where('lookup_limit', 250)
                ->has(
                    'recipients.0',
                    fn (Assert $recipient) => $recipient
                        ->hasAll([
                            'id',
                            'code',
                            'display_name',
                            'type',
                            'is_active',
                            'is_available',
                            'investor',
                        ])
                        ->missing('investor_id')
                        ->missing('created_at')
                        ->missing('updated_at')
                        ->where('display_name', '000 Investor Recipient')
                        ->has(
                            'investor',
                            fn (Assert $investor) => $investor
                                ->hasAll([
                                    'id',
                                    'name',
                                    'is_active',
                                ])
                                ->missing('email')
                                ->missing('phone')
                        )
                )
        );
        $investorQueries = array_filter(
            $queries,
            static fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "investors"'
            )
        );

        $this->assertCount(
            2,
            $investorQueries,
            'Expected one bounded recipient query with an Investor existence predicate and one eager-load query.'
        );
    }
}
