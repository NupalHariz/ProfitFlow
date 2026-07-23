<?php

namespace Tests\Feature\Actions\ProfitSchemes;

use App\Exceptions\ProfitSchemes\InvalidProfitSchemeAllocation;
use App\Exceptions\ProfitSchemes\ProfitSchemeVersionImmutable;
use App\Models\ProfitSchemeAllocation;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class ReplaceDraftProfitSchemeAllocationsTest extends ProfitSchemeActionTestCase
{
    public function test_valid_draft_allocations_are_replaced_atomically(): void
    {
        $version = $this->createDraftVersion();
        $oldRecipient = $this->createRecipient();
        $first = $this->createRecipient();
        $second = $this->createRecipient();
        ProfitSchemeAllocation::factory()
            ->for($version, 'version')
            ->for($oldRecipient, 'recipient')
            ->create(['percentage' => '100.0000']);

        $updated = $this->replaceAllocations($version, [
            [
                'profit_recipient_id' => $first->id,
                'percentage' => '40',
            ],
            [
                'profit_recipient_id' => $second->id,
                'percentage' => '60.0',
            ],
        ]);

        $this->assertTrue($updated->relationLoaded('allocations'));
        $this->assertSame(
            [$first->id, $second->id],
            $updated->allocations
                ->pluck('profit_recipient_id')
                ->all()
        );
        $this->assertSame(
            ['40.0000', '60.0000'],
            $updated->allocations->pluck('percentage')->all()
        );
        $this->assertDatabaseMissing('profit_scheme_allocations', [
            'profit_scheme_version_id' => $version->id,
            'profit_recipient_id' => $oldRecipient->id,
        ]);
    }

    public function test_input_order_is_preserved_in_allocation_id_order(): void
    {
        $version = $this->createDraftVersion();
        $recipients = [
            $this->createRecipient(),
            $this->createRecipient(),
            $this->createRecipient(),
        ];
        $inputOrder = [
            $recipients[2]->id,
            $recipients[0]->id,
            $recipients[1]->id,
        ];

        $updated = $this->replaceAllocations($version, [
            [
                'profit_recipient_id' => $inputOrder[0],
                'percentage' => '33.3333',
            ],
            [
                'profit_recipient_id' => $inputOrder[1],
                'percentage' => '33.3333',
            ],
            [
                'profit_recipient_id' => $inputOrder[2],
                'percentage' => '33.3334',
            ],
        ]);

        $this->assertSame(
            $inputOrder,
            $updated->allocations
                ->sortBy('id')
                ->pluck('profit_recipient_id')
                ->values()
                ->all()
        );
    }

    public function test_duplicate_recipient_is_rejected(): void
    {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient();

        $this->expectException(InvalidProfitSchemeAllocation::class);
        $this->expectExceptionMessage('duplicated');

        $this->replaceAllocations($version, [
            [
                'profit_recipient_id' => $recipient->id,
                'percentage' => '50.0000',
            ],
            [
                'profit_recipient_id' => $recipient->id,
                'percentage' => '50.0000',
            ],
        ]);
    }

    public function test_missing_recipient_is_rejected(): void
    {
        $version = $this->createDraftVersion();

        $this->expectException(InvalidProfitSchemeAllocation::class);
        $this->expectExceptionMessage('does not exist');

        $this->replaceAllocations($version, [[
            'profit_recipient_id' => 999_999,
            'percentage' => '100.0000',
        ]]);
    }

    public function test_inactive_recipient_is_rejected(): void
    {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient(active: false);

        $this->expectException(InvalidProfitSchemeAllocation::class);
        $this->expectExceptionMessage('inactive');

        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
    }

    #[DataProvider('invalidPercentages')]
    public function test_invalid_individual_percentage_is_rejected(
        string $percentage,
        string $message
    ): void {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient();

        $this->expectException(InvalidProfitSchemeAllocation::class);
        $this->expectExceptionMessage($message);

        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => $percentage,
        ]]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidPercentages(): array
    {
        return [
            'zero' => ['0.0000', 'greater than zero'],
            'negative' => ['-0.0001', 'greater than zero'],
            'above one hundred' => ['100.0001', 'cannot exceed'],
            'malformed' => ['not-a-percentage', 'decimal(7,4)'],
            'excess precision' => ['1.00001', 'decimal(7,4)'],
        ];
    }

    public function test_published_version_allocation_replacement_is_rejected(): void
    {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $published = $this->publish($version);

        $this->expectException(ProfitSchemeVersionImmutable::class);
        $this->expectExceptionMessage('Published');

        $this->replaceAllocations($published, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '99.0000',
        ]]);
    }

    public function test_referenced_version_allocation_replacement_is_rejected(): void
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

        $this->replaceAllocations($version, []);
    }

    public function test_failed_validation_preserves_previous_allocation_set(): void
    {
        $version = $this->createDraftVersion();
        $first = $this->createRecipient();
        $second = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $first->id,
            'percentage' => '100.0000',
        ]]);
        $before = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->get(['id', 'profit_recipient_id', 'percentage'])
            ->toArray();

        try {
            $this->replaceAllocations($version, [
                [
                    'profit_recipient_id' => $second->id,
                    'percentage' => '50.0000',
                ],
                [
                    'profit_recipient_id' => $second->id,
                    'percentage' => '50.0000',
                ],
            ]);
            $this->fail('Expected duplicate validation to fail.');
        } catch (InvalidProfitSchemeAllocation) {
            // Expected.
        }

        $after = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->get(['id', 'profit_recipient_id', 'percentage'])
            ->toArray();

        $this->assertSame($before, $after);
    }

    #[DataProvider('draftTotals')]
    public function test_draft_total_need_not_equal_one_hundred(
        string $firstPercentage,
        string $secondPercentage
    ): void {
        $version = $this->createDraftVersion();
        $first = $this->createRecipient();
        $second = $this->createRecipient();

        $updated = $this->replaceAllocations($version, [
            [
                'profit_recipient_id' => $first->id,
                'percentage' => $firstPercentage,
            ],
            [
                'profit_recipient_id' => $second->id,
                'percentage' => $secondPercentage,
            ],
        ]);

        $this->assertCount(2, $updated->allocations);
        $this->assertNull($updated->published_at);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function draftTotals(): array
    {
        return [
            'below one hundred' => ['20.0000', '30.0000'],
            'above one hundred' => ['60.0000', '50.0000'],
        ];
    }

    public function test_empty_draft_allocation_set_is_allowed(): void
    {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);

        $updated = $this->replaceAllocations($version, []);

        $this->assertCount(0, $updated->allocations);
        $this->assertDatabaseMissing('profit_scheme_allocations', [
            'profit_scheme_version_id' => $version->id,
        ]);
    }

    public function test_insertion_exception_rolls_back_all_allocation_changes(): void
    {
        $version = $this->createDraftVersion();
        $oldRecipient = $this->createRecipient();
        $first = $this->createRecipient();
        $second = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $oldRecipient->id,
            'percentage' => '100.0000',
        ]]);
        $before = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->get(['id', 'profit_recipient_id', 'percentage'])
            ->toArray();
        $creationCount = 0;

        ProfitSchemeAllocation::creating(
            static function () use (&$creationCount): void {
                $creationCount++;

                if ($creationCount === 2) {
                    throw new RuntimeException(
                        'Simulated allocation insert failure.'
                    );
                }
            }
        );

        try {
            $this->replaceAllocations($version, [
                [
                    'profit_recipient_id' => $first->id,
                    'percentage' => '50.0000',
                ],
                [
                    'profit_recipient_id' => $second->id,
                    'percentage' => '50.0000',
                ],
            ]);
            $this->fail('Expected a simulated insertion failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated allocation insert failure.',
                $exception->getMessage()
            );
        } finally {
            ProfitSchemeAllocation::flushEventListeners();
        }

        $after = ProfitSchemeAllocation::query()
            ->where('profit_scheme_version_id', $version->id)
            ->get(['id', 'profit_recipient_id', 'percentage'])
            ->toArray();

        $this->assertSame($before, $after);
    }
}
