<?php

namespace Tests\Feature\Http\ProfitSchemes;

use App\Models\ProfitSchemeAllocation;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

class ProfitSchemeAllocationAndPublicationTest extends ProfitSchemeHttpTestCase
{
    public function test_valid_ordered_allocations_persist_in_payload_order(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $first = $this->recipient();
        $second = $this->recipient();

        $this->actingAs($user)
            ->put(route('profit-schemes.versions.allocations.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'allocations' => [
                    [
                        'profit_recipient_id' => $second->id,
                        'percentage' => '60.0000',
                    ],
                    [
                        'profit_recipient_id' => $first->id,
                        'percentage' => '40.0000',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $stored = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->orderBy('id')
            ->get();

        $this->assertSame(
            [$second->id, $first->id],
            $stored->pluck('profit_recipient_id')->all()
        );
        $this->assertSame(
            ['60.0000', '40.0000'],
            $stored->pluck('percentage')->all()
        );
    }

    public function test_draft_totals_below_and_above_one_hundred_can_be_saved(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $first = $this->recipient();
        $second = $this->recipient();

        $this->actingAs($user)
            ->put(route('profit-schemes.versions.allocations.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'allocations' => [[
                    'profit_recipient_id' => $first->id,
                    'percentage' => '40.0000',
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('profit_scheme_allocations', [
            'profit_scheme_version_id' => $version->id,
            'percentage' => '40.0000',
        ]);

        $this->actingAs($user)
            ->put(route('profit-schemes.versions.allocations.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'allocations' => [
                    [
                        'profit_recipient_id' => $first->id,
                        'percentage' => '60.0000',
                    ],
                    [
                        'profit_recipient_id' => $second->id,
                        'percentage' => '60.0000',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['60.0000', '60.0000'],
            ProfitSchemeAllocation::query()
                ->where('profit_scheme_version_id', $version->id)
                ->orderBy('id')
                ->pluck('percentage')
                ->all()
        );
    }

    public function test_duplicate_recipients_return_http_validation_errors(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $recipient = $this->recipient();

        $this->actingAs($user)
            ->put(route('profit-schemes.versions.allocations.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'allocations' => [
                    [
                        'profit_recipient_id' => $recipient->id,
                        'percentage' => '50.0000',
                    ],
                    [
                        'profit_recipient_id' => $recipient->id,
                        'percentage' => '50.0000',
                    ],
                ],
            ])
            ->assertSessionHasErrors(
                'allocations.1.profit_recipient_id'
            );

        $this->assertDatabaseCount('profit_scheme_allocations', 0);
    }

    public function test_inactive_recipient_cannot_be_newly_assigned(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $recipient = $this->recipient(active: false);

        $this->actingAs($user)
            ->put(route('profit-schemes.versions.allocations.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'allocations' => [[
                    'profit_recipient_id' => $recipient->id,
                    'percentage' => '100.0000',
                ]],
            ])
            ->assertSessionHasErrors(
                'allocations.0.profit_recipient_id'
            );

        $this->assertDatabaseCount('profit_scheme_allocations', 0);
    }

    public function test_published_version_allocation_change_is_rejected(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $recipient = $this->recipient();
        $replacement = $this->recipient();
        $this->allocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $this->publish($version);

        $this->actingAs($user)
            ->put(route('profit-schemes.versions.allocations.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'allocations' => [[
                    'profit_recipient_id' => $replacement->id,
                    'percentage' => '100.0000',
                ]],
            ])
            ->assertSessionHasErrors('version');

        $this->assertDatabaseHas('profit_scheme_allocations', [
            'profit_scheme_version_id' => $version->id,
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]);
    }

    public function test_failed_replacement_preserves_prior_allocation_set(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $recipient = $this->recipient();
        $this->allocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $before = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->get()
            ->toArray();

        $this->actingAs($user)
            ->put(route('profit-schemes.versions.allocations.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'allocations' => [[
                    'profit_recipient_id' => 999_999,
                    'percentage' => '100.0000',
                ]],
            ])
            ->assertSessionHasErrors(
                'allocations.0.profit_recipient_id'
            );

        $after = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->get()
            ->toArray();

        $this->assertSame($before, $after);
    }

    public function test_exact_one_hundred_percent_version_publishes(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $recipient = $this->recipient();
        $this->allocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);

        $this->actingAs($user)
            ->post(route('profit-schemes.versions.publish', [
                'scheme' => $scheme,
                'version' => $version,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profit-schemes.show', $scheme));

        $this->assertNotNull($version->fresh()->published_at);
    }

    public function test_below_and_above_one_hundred_publication_return_validation_errors(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $first = $this->recipient();
        $second = $this->recipient();
        $below = $this->draft($scheme, $user);
        $this->allocations($below, [[
            'profit_recipient_id' => $first->id,
            'percentage' => '99.9999',
        ]]);
        $above = $this->draft($scheme, $user);
        $this->allocations($above, [
            [
                'profit_recipient_id' => $first->id,
                'percentage' => '50.0001',
            ],
            [
                'profit_recipient_id' => $second->id,
                'percentage' => '50.0000',
            ],
        ]);

        foreach ([$below, $above] as $version) {
            $this->actingAs($user)
                ->post(route('profit-schemes.versions.publish', [
                    'scheme' => $scheme,
                    'version' => $version,
                ]))
                ->assertSessionHasErrors('allocations');

            $this->assertNull($version->fresh()->published_at);
        }
    }

    public function test_inactive_version_publication_returns_validation_error(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user, [
            'is_active' => false,
        ]);
        $recipient = $this->recipient();
        $this->allocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);

        $this->actingAs($user)
            ->post(route('profit-schemes.versions.publish', [
                'scheme' => $scheme,
                'version' => $version,
            ]))
            ->assertSessionHasErrors('version');

        $this->assertNull($version->fresh()->published_at);
    }

    public function test_published_version_is_read_only_in_detail_props(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $recipient = $this->recipient();
        $this->allocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $this->publish($version);

        $this->actingAs($user)
            ->get(route('profit-schemes.show', $scheme))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('scheme.versions.0.is_published', true)
                    ->where('scheme.versions.0.is_immutable', true)
                    ->where('scheme.versions.0.can_edit', false)
                    ->where(
                        'scheme.versions.0.is_ready_to_publish',
                        false
                    )
            );
    }

    public function test_repeated_publish_requests_are_idempotent(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $recipient = $this->recipient();
        $this->allocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        CarbonImmutable::setTestNow('2026-07-23 10:00:00');

        try {
            $this->actingAs($user)
                ->post(route('profit-schemes.versions.publish', [
                    'scheme' => $scheme,
                    'version' => $version,
                ]))
                ->assertSessionHasNoErrors();
            $firstTimestamp = $version->fresh()
                ->published_at
                ->toDateTimeString();
            CarbonImmutable::setTestNow('2026-07-24 10:00:00');

            $this->actingAs($user)
                ->post(route('profit-schemes.versions.publish', [
                    'scheme' => $scheme,
                    'version' => $version,
                ]))
                ->assertSessionHasNoErrors();
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame(
            $firstTimestamp,
            $version->fresh()->published_at->toDateTimeString()
        );
        $this->assertDatabaseCount('profit_scheme_allocations', 1);
    }

    public function test_published_historical_values_remain_unchanged_after_http_mutation_attempts(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user, ['priority' => 77]);
        $recipient = $this->recipient();
        $replacement = $this->recipient();
        $this->allocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $this->publish($version);
        $beforeVersion = $version->fresh()->getRawOriginal();
        $beforeAllocations = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->get()
            ->toArray();

        $this->actingAs($user)
            ->patch(route('profit-schemes.versions.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), ['priority' => 999])
            ->assertSessionHasErrors('version');
        $this->actingAs($user)
            ->put(route('profit-schemes.versions.allocations.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'allocations' => [[
                    'profit_recipient_id' => $replacement->id,
                    'percentage' => '100.0000',
                ]],
            ])
            ->assertSessionHasErrors('version');

        $afterVersion = $version->fresh()->getRawOriginal();
        $afterAllocations = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->get()
            ->toArray();

        $this->assertSame($beforeVersion, $afterVersion);
        $this->assertSame($beforeAllocations, $afterAllocations);
    }
}
