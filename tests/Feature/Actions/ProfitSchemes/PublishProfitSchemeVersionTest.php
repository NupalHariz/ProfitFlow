<?php

namespace Tests\Feature\Actions\ProfitSchemes;

use App\Actions\ProfitSchemes\PublishProfitSchemeVersion;
use App\Actions\ProfitSchemes\ReplaceDraftProfitSchemeAllocations;
use App\Actions\ProfitSchemes\UpdateDraftProfitSchemeVersion;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeAllocation;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeVersion;
use App\Exceptions\ProfitSchemes\ProfitSchemeVersionImmutable;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use App\Models\User;
use App\Services\ProfitSharing\ProfitRuleResolver;
use Carbon\CarbonImmutable;
use RuntimeException;

class PublishProfitSchemeVersionTest extends ProfitSchemeActionTestCase
{
    public function test_valid_exact_allocation_total_publishes_successfully(): void
    {
        $version = $this->createDraftVersion();
        $first = $this->createRecipient();
        $second = $this->createRecipient();
        $this->replaceAllocations($version, [
            [
                'profit_recipient_id' => $first->id,
                'percentage' => '40.0000',
            ],
            [
                'profit_recipient_id' => $second->id,
                'percentage' => '60.0000',
            ],
        ]);

        $published = $this->publish($version);

        $this->assertNotNull($published->published_at);
        $this->assertTrue($published->relationLoaded('allocations'));
        $this->assertCount(2, $published->allocations);
    }

    public function test_publication_timestamp_uses_application_clock(): void
    {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        CarbonImmutable::setTestNow('2026-07-15 12:34:56');

        try {
            $published = $this->publish($version);
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame(
            '2026-07-15 12:34:56',
            $published->published_at->format('Y-m-d H:i:s')
        );
    }

    public function test_empty_allocation_set_is_rejected(): void
    {
        $version = $this->createDraftVersion();

        $this->expectException(InvalidProfitSchemeAllocation::class);
        $this->expectExceptionMessage('at least one allocation');

        $this->publish($version);
    }

    public function test_total_below_one_hundred_is_rejected(): void
    {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '99.9999',
        ]]);

        $this->expectException(InvalidProfitSchemeAllocation::class);
        $this->expectExceptionMessage('exactly 100.0000');

        $this->publish($version);
    }

    public function test_total_above_one_hundred_is_rejected(): void
    {
        $version = $this->createDraftVersion();
        $first = $this->createRecipient();
        $second = $this->createRecipient();
        $this->replaceAllocations($version, [
            [
                'profit_recipient_id' => $first->id,
                'percentage' => '50.0001',
            ],
            [
                'profit_recipient_id' => $second->id,
                'percentage' => '50.0000',
            ],
        ]);

        $this->expectException(InvalidProfitSchemeAllocation::class);
        $this->expectExceptionMessage('exactly 100.0000');

        $this->publish($version);
    }

    public function test_difficult_exact_percentage_total_publishes(): void
    {
        $version = $this->createDraftVersion();
        $first = $this->createRecipient();
        $second = $this->createRecipient();
        $third = $this->createRecipient();
        $this->replaceAllocations($version, [
            [
                'profit_recipient_id' => $first->id,
                'percentage' => '33.3333',
            ],
            [
                'profit_recipient_id' => $second->id,
                'percentage' => '33.3333',
            ],
            [
                'profit_recipient_id' => $third->id,
                'percentage' => '33.3334',
            ],
        ]);

        $published = $this->publish($version);

        $this->assertNotNull($published->published_at);
    }

    public function test_inactive_recipient_causes_publish_rejection(): void
    {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $recipient->update(['is_active' => false]);

        $this->expectException(InvalidProfitSchemeAllocation::class);
        $this->expectExceptionMessage('no longer available');

        $this->publish($version);
    }

    public function test_inactive_version_cannot_be_published(): void
    {
        $version = $this->createDraftVersion(overrides: [
            'is_active' => false,
        ]);
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);

        $this->expectException(InvalidProfitSchemeVersion::class);
        $this->expectExceptionMessage(
            'Inactive profit scheme versions cannot be published.'
        );

        $this->publish($version);
    }

    public function test_category_null_draft_cannot_be_published(): void
    {
        $version = ProfitSchemeVersion::factory()->create([
            'product_category_id' => null,
        ]);
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);

        $this->expectException(InvalidProfitSchemeVersion::class);
        $this->expectExceptionMessage('Product category is required');

        $this->publish($version);
    }

    public function test_failed_inactive_publication_leaves_version_and_allocations_unchanged(): void
    {
        $version = $this->createDraftVersion(overrides: [
            'minimum_profit' => '10.00',
            'maximum_profit' => '500.00',
            'priority' => 25,
            'is_active' => false,
        ]);
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $beforeAttributes = $version->fresh()->getRawOriginal();
        $beforeAllocations = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->get(['id', 'profit_recipient_id', 'percentage'])
            ->toArray();

        try {
            $this->publish($version);
            $this->fail('Expected inactive publication to fail.');
        } catch (InvalidProfitSchemeVersion $exception) {
            $this->assertStringContainsString(
                'Inactive profit scheme version',
                $exception->getMessage()
            );
        }

        $fresh = $version->fresh();
        $afterAllocations = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->get(['id', 'profit_recipient_id', 'percentage'])
            ->toArray();

        $this->assertNotNull($fresh);
        $this->assertNull($fresh->published_at);
        $this->assertFalse($fresh->is_active);
        $this->assertSame(
            $beforeAttributes['minimum_profit'],
            $fresh->getRawOriginal('minimum_profit')
        );
        $this->assertSame(
            $beforeAttributes['maximum_profit'],
            $fresh->getRawOriginal('maximum_profit')
        );
        $this->assertSame(
            $beforeAttributes['priority'],
            $fresh->getRawOriginal('priority')
        );
        $this->assertSame(
            $beforeAttributes['updated_at'],
            $fresh->getRawOriginal('updated_at')
        );
        $this->assertSame($beforeAllocations, $afterAllocations);
    }

    public function test_active_valid_version_publishes_when_parent_scheme_is_inactive(): void
    {
        $creator = User::factory()->create();
        $scheme = ProfitScheme::factory()
            ->for($creator, 'creator')
            ->create(['is_active' => false]);
        $version = $this->createDraftVersion(
            $scheme,
            $creator,
            ['is_active' => true]
        );
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);

        $published = $this->publish($version);

        $this->assertTrue($published->is_active);
        $this->assertNotNull($published->published_at);
        $this->assertFalse($published->scheme->is_active);
    }

    public function test_duplicate_publish_is_an_idempotent_no_op(): void
    {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        CarbonImmutable::setTestNow('2026-07-15 10:00:00');

        try {
            $firstPublish = $this->publish($version);
            $firstTimestamp = $firstPublish->published_at->toDateTimeString();
            $firstUpdatedAt = $firstPublish->updated_at->toDateTimeString();
            $recipient->update(['is_active' => false]);
            CarbonImmutable::setTestNow('2026-07-16 10:00:00');

            $secondPublish = $this->publish($firstPublish);
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame(
            $firstTimestamp,
            $secondPublish->published_at->toDateTimeString()
        );
        $this->assertSame(
            $firstUpdatedAt,
            $secondPublish->updated_at->toDateTimeString()
        );
        $this->assertSame(
            ['100.0000'],
            $secondPublish->allocations->pluck('percentage')->all()
        );
    }

    public function test_failed_publish_leaves_publication_timestamp_null(): void
    {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '90.0000',
        ]]);

        try {
            $this->publish($version);
            $this->fail('Expected publication validation to fail.');
        } catch (InvalidProfitSchemeAllocation) {
            // Expected.
        }

        $this->assertNull($version->fresh()->published_at);
    }

    public function test_published_fields_and_allocations_remain_unchanged_after_mutation_attempts(): void
    {
        $version = $this->createDraftVersion(overrides: ['priority' => 15]);
        $recipient = $this->createRecipient();
        $replacementRecipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $published = $this->publish($version);
        $beforeAttributes = $published->getRawOriginal();
        $beforeAllocations = $published->allocations
            ->map(fn (ProfitSchemeAllocation $allocation): array => [
                'id' => $allocation->id,
                'profit_recipient_id' => $allocation->profit_recipient_id,
                'percentage' => $allocation->percentage,
            ])
            ->all();

        foreach ([
            fn () => app(UpdateDraftProfitSchemeVersion::class)
                ->execute($published, ['priority' => 99]),
            fn () => app(ReplaceDraftProfitSchemeAllocations::class)
                ->execute($published, [[
                    'profit_recipient_id' => $replacementRecipient->id,
                    'percentage' => '100.0000',
                ]]),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Expected published-version immutability.');
            } catch (ProfitSchemeVersionImmutable) {
                // Expected.
            }
        }

        $fresh = $published->fresh();
        $afterAllocations = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $published->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ProfitSchemeAllocation $allocation): array => [
                'id' => $allocation->id,
                'profit_recipient_id' => $allocation->profit_recipient_id,
                'percentage' => $allocation->percentage,
            ])
            ->all();

        $this->assertNotNull($fresh);
        $this->assertSame(
            $beforeAttributes['priority'],
            $fresh->getRawOriginal('priority')
        );
        $this->assertSame(
            $beforeAttributes['published_at'],
            $fresh->getRawOriginal('published_at')
        );
        $this->assertSame($beforeAllocations, $afterAllocations);
    }

    public function test_referenced_unpublished_version_cannot_be_published(): void
    {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $this->referenceVersion($version);

        $this->expectException(ProfitSchemeVersionImmutable::class);
        $this->expectExceptionMessage('Historically referenced');

        $this->publish($version);
    }

    public function test_newly_published_version_is_resolvable(): void
    {
        $version = $this->createDraftVersion(overrides: [
            'minimum_profit' => '100.00',
            'maximum_profit' => '500.00',
            'effective_from' => '2026-07-01',
            'effective_until' => '2026-07-31',
            'priority' => 50,
        ]);
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $published = $this->publish($version);

        $resolved = app(ProfitRuleResolver::class)->resolve(
            '2026-07-15',
            $published->product_category_id,
            '250.00'
        );

        $this->assertNotNull($resolved);
        $this->assertSame($published->id, $resolved->id);
    }

    public function test_newer_version_does_not_modify_older_version_or_allocations(): void
    {
        $creator = User::factory()->create();
        $scheme = ProfitScheme::factory()
            ->for($creator, 'creator')
            ->create();
        $olderRecipient = $this->createRecipient();
        $newerRecipient = $this->createRecipient();
        $older = $this->createDraftVersion(
            $scheme,
            $creator,
            ['priority' => 10]
        );
        $this->replaceAllocations($older, [[
            'profit_recipient_id' => $olderRecipient->id,
            'percentage' => '100.0000',
        ]]);
        $publishedOlder = $this->publish($older);
        $olderAttributes = $publishedOlder->getRawOriginal();
        $olderAllocationIds = $publishedOlder->allocations->pluck('id')->all();

        $newer = $this->createDraftVersion(
            $scheme,
            $creator,
            ['priority' => 20]
        );
        $this->replaceAllocations($newer, [[
            'profit_recipient_id' => $newerRecipient->id,
            'percentage' => '100.0000',
        ]]);
        $publishedNewer = $this->publish($newer);

        $freshOlder = $publishedOlder->fresh();

        $this->assertNotNull($freshOlder);
        $this->assertSame(1, $freshOlder->version);
        $this->assertSame(2, $publishedNewer->version);
        $this->assertSame(
            $olderAttributes['priority'],
            $freshOlder->getRawOriginal('priority')
        );
        $this->assertSame(
            $olderAttributes['published_at'],
            $freshOlder->getRawOriginal('published_at')
        );
        $this->assertSame(
            $olderAllocationIds,
            $freshOlder->allocations()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        );
    }

    public function test_publish_exception_rolls_back_version_and_preserves_allocations(): void
    {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $beforeAllocations = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->get(['id', 'profit_recipient_id', 'percentage'])
            ->toArray();

        ProfitSchemeVersion::updating(
            static function (): void {
                throw new RuntimeException(
                    'Simulated publication persistence failure.'
                );
            }
        );

        try {
            app(PublishProfitSchemeVersion::class)->execute($version);
            $this->fail('Expected a simulated publication failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated publication persistence failure.',
                $exception->getMessage()
            );
        } finally {
            ProfitSchemeVersion::flushEventListeners();
        }

        $afterAllocations = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->get(['id', 'profit_recipient_id', 'percentage'])
            ->toArray();

        $this->assertNull($version->fresh()->published_at);
        $this->assertSame($beforeAllocations, $afterAllocations);
    }
}
