<?php

namespace Tests\Feature\Http\ProfitSchemes;

use App\Models\ProfitScheme;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class ProfitSchemeManagementTest extends ProfitSchemeHttpTestCase
{
    public function test_create_page_renders(): void
    {
        $this->actingAs($this->user())
            ->get(route('profit-schemes.create'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('profit-schemes/create')
            );
    }

    public function test_valid_scheme_creation_uses_domain_normalization(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('profit-schemes.store'), [
                'name' => '  Enterprise Sharing  ',
                'code' => 'Enterprise Sharing',
                'description' => 'Primary enterprise rule family.',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('profit_schemes', [
            'name' => 'Enterprise Sharing',
            'code' => 'enterprise-sharing',
            'description' => 'Primary enterprise rule family.',
            'is_active' => true,
        ]);
    }

    public function test_scheme_creator_is_the_authenticated_user(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('profit-schemes.store'), [
                'name' => 'Creator Audit Scheme',
                'code' => 'creator-audit',
                'description' => null,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('profit_schemes', [
            'code' => 'creator-audit',
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_invalid_scheme_input_returns_validation_errors(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from(route('profit-schemes.create'))
            ->post(route('profit-schemes.store'), [
                'name' => '',
                'code' => '',
                'is_active' => 'not-a-boolean',
            ])
            ->assertRedirect(route('profit-schemes.create'))
            ->assertSessionHasErrors(['name', 'code', 'is_active']);

        $this->assertDatabaseCount('profit_schemes', 0);
    }

    public function test_duplicate_normalized_code_is_a_validation_error(): void
    {
        $user = $this->user();
        $this->scheme($user, ['code' => 'enterprise-sharing']);

        $this->actingAs($user)
            ->from(route('profit-schemes.create'))
            ->post(route('profit-schemes.store'), [
                'name' => 'Duplicate',
                'code' => 'Enterprise Sharing',
                'description' => null,
                'is_active' => true,
            ])
            ->assertRedirect(route('profit-schemes.create'))
            ->assertSessionHasErrors('scheme');

        $this->assertDatabaseCount('profit_schemes', 1);
    }

    public function test_successful_creation_redirects_to_scheme_detail(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)
            ->post(route('profit-schemes.store'), [
                'name' => 'Redirect Scheme',
                'code' => 'redirect-scheme',
                'description' => null,
                'is_active' => true,
            ]);
        $scheme = ProfitScheme::query()
            ->where('code', 'redirect-scheme')
            ->firstOrFail();

        $response
            ->assertRedirect(route('profit-schemes.show', $scheme))
            ->assertSessionHasNoErrors();
    }

    public function test_detail_displays_versions_newest_first(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $this->draft($scheme, $user);
        $this->draft($scheme, $user);
        $this->draft($scheme, $user);

        $this->actingAs($user)
            ->get(route('profit-schemes.show', $scheme))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('scheme.versions', 3)
                    ->where('scheme.versions.0.version', 3)
                    ->where('scheme.versions.1.version', 2)
                    ->where('scheme.versions.2.version', 1)
            );
    }

    public function test_detail_includes_ordered_allocations_and_recipient_summaries(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $first = $this->recipient();
        $second = $this->recipient();
        $this->allocations($version, [
            [
                'profit_recipient_id' => $second->id,
                'percentage' => '60.0000',
            ],
            [
                'profit_recipient_id' => $first->id,
                'percentage' => '40.0000',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('profit-schemes.show', $scheme))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('scheme.versions.0.allocations', 2)
                    ->where(
                        'scheme.versions.0.allocations.0.recipient.id',
                        $second->id
                    )
                    ->where(
                        'scheme.versions.0.allocations.0.percentage',
                        '60.0000'
                    )
                    ->where(
                        'scheme.versions.0.allocations.1.recipient.id',
                        $first->id
                    )
                    ->has(
                        'scheme.versions.0.allocations.0.recipient',
                        fn (Assert $recipient) => $recipient
                            ->hasAll([
                                'id',
                                'code',
                                'display_name',
                                'type',
                                'is_active',
                                'is_available_for_allocation',
                            ])
                            ->where(
                                'is_available_for_allocation',
                                true
                            )
                    )
            );
    }

    public function test_detail_exposes_draft_published_and_immutable_states(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $draft = $this->draft($scheme, $user);
        $published = $this->draft($scheme, $user);
        $recipient = $this->recipient();
        $this->allocations($published, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $this->publish($published);
        $referenced = $this->draft($scheme, $user);
        $this->reference($referenced);

        $this->actingAs($user)
            ->get(route('profit-schemes.show', $scheme))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'scheme.versions.0.is_historically_referenced',
                        true
                    )
                    ->where('scheme.versions.0.is_immutable', true)
                    ->where('scheme.versions.0.can_edit', false)
                    ->where('scheme.versions.1.is_published', true)
                    ->where('scheme.versions.1.is_immutable', true)
                    ->where('scheme.versions.1.can_edit', false)
                    ->where('scheme.versions.2.id', $draft->id)
                    ->where('scheme.versions.2.lifecycle_status', 'draft')
                    ->where('scheme.versions.2.is_immutable', false)
                    ->where('scheme.versions.2.can_edit', true)
            );
    }

    public function test_detail_computes_current_superseded_and_scheduled_version_states(): void
    {
        CarbonImmutable::setTestNow('2026-07-24 12:00:00');
        $user = $this->user();
        $scheme = $this->scheme($user);
        $recipient = $this->recipient();
        $first = $this->draft($scheme, $user, [
            'effective_from' => '2026-07-01',
        ]);
        $second = $this->draft($scheme, $user, [
            'effective_from' => '2026-07-15',
        ]);
        $scheduled = $this->draft($scheme, $user, [
            'effective_from' => '2026-08-01',
        ]);

        foreach ([$first, $second, $scheduled] as $version) {
            $this->allocations($version, [[
                'profit_recipient_id' => $recipient->id,
                'percentage' => '100.0000',
            ]]);
            $this->publish($version);
        }

        $this->actingAs($user)
            ->get(route('profit-schemes.show', $scheme))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'scheme.versions.0.version_status',
                        'scheduled'
                    )
                    ->where('scheme.versions.0.is_published', true)
                    ->where('scheme.versions.0.is_immutable', true)
                    ->where(
                        'scheme.versions.1.version_status',
                        'current'
                    )
                    ->where(
                        'scheme.versions.2.version_status',
                        'superseded'
                    )
            );
    }

    public function test_detail_marks_selected_current_version_inactive_without_resurrecting_older_version(): void
    {
        CarbonImmutable::setTestNow('2026-07-24 12:00:00');
        $user = $this->user();
        $scheme = $this->scheme($user);
        $recipient = $this->recipient();
        $first = $this->draft($scheme, $user);
        $second = $this->draft($scheme, $user);

        foreach ([$first, $second] as $version) {
            $this->allocations($version, [[
                'profit_recipient_id' => $recipient->id,
                'percentage' => '100.0000',
            ]]);
            $this->publish($version);
        }

        $second->update(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('profit-schemes.show', $scheme))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'scheme.versions.0.version_status',
                        'current'
                    )
                    ->where('scheme.versions.0.is_active', false)
                    ->where(
                        'scheme.versions.1.version_status',
                        'superseded'
                    )
            );
    }

    public function test_detail_query_count_does_not_grow_with_version_and_allocation_rows(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $recipients = [
            $this->recipient(),
            $this->recipient(),
            $this->recipient(),
        ];

        foreach (range(1, 5) as $priority) {
            $version = $this->draft($scheme, $user, [
                'priority' => $priority,
            ]);
            $this->allocations($version, [
                [
                    'profit_recipient_id' => $recipients[0]->id,
                    'percentage' => '33.3333',
                ],
                [
                    'profit_recipient_id' => $recipients[1]->id,
                    'percentage' => '33.3333',
                ],
                [
                    'profit_recipient_id' => $recipients[2]->id,
                    'percentage' => '33.3334',
                ],
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)
            ->get(route('profit-schemes.show', $scheme))
            ->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(8, $queryCount);
    }
}
