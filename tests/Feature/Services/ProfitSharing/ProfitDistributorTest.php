<?php

namespace Tests\Feature\Services\ProfitSharing;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidProfitDistribution;
use App\Exceptions\Transactions\TransactionDistributionNotProcessable;
use App\Models\ProfitDistribution;
use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeAllocation;
use App\Services\ProfitSharing\ProfitRuleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class ProfitDistributorTest extends ProfitDistributionTestCase
{
    public function test_distribution_never_resolves_current_rules(): void
    {
        $frozen = $this->createFrozenVersion();
        $transaction = $this->createPendingTransaction([[
            'profit' => '10.00',
            'version' => $frozen['version'],
        ]]);
        app()->bind(
            ProfitRuleResolver::class,
            static fn (): never => throw new \RuntimeException(
                'The distributor must not resolve current rules.'
            )
        );

        $distributed = $this->distribute($transaction);

        $this->assertSame(
            TransactionStatus::Distributed,
            $distributed->status
        );
        $this->assertSame(
            $frozen['version']->id,
            ProfitDistribution::query()
                ->sole()
                ->profit_scheme_version_id
        );
    }

    public function test_it_persists_exact_item_level_snapshots_and_marks_the_transaction_distributed(): void
    {
        CarbonImmutable::setTestNow('2026-07-23 10:11:12');
        $frozen = $this->createFrozenVersion([
            '40.0000',
            '30.0000',
            '30.0000',
        ]);
        $transaction = $this->createPendingTransaction([[
            'profit' => '100.00',
            'version' => $frozen['version'],
        ]]);
        $item = $this->orderedItems($transaction)[0];
        $headerBefore = Arr::except(
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail(),
            ['status', 'failure_reason', 'distribution_started_at',
                'distributed_at', 'updated_at']
        );
        $itemBefore = (array) DB::table('transaction_items')
            ->where('id', $item->id)
            ->firstOrFail();

        $distributed = $this->distribute($transaction);

        $this->assertSame(
            TransactionStatus::Distributed,
            $distributed->status
        );
        $this->assertSame(
            '2026-07-23 10:11:12',
            $distributed->distribution_started_at?->format(
                'Y-m-d H:i:s'
            )
        );
        $this->assertSame(
            '2026-07-23 10:11:12',
            $distributed->distributed_at?->format('Y-m-d H:i:s')
        );
        $this->assertNull($distributed->failure_reason);
        $rows = ProfitDistribution::query()->orderBy('id')->get();
        $this->assertSame(
            ['40.00', '30.00', '30.00'],
            $rows->pluck('amount')->all()
        );
        $this->assertSame(
            ['40.0000', '30.0000', '30.0000'],
            $rows->pluck('percentage')->all()
        );
        $this->assertSame(
            collect($frozen['recipients'])->pluck('id')->all(),
            $rows->pluck('profit_recipient_id')->all()
        );
        $this->assertSame(
            collect($frozen['recipients'])
                ->pluck('display_name')
                ->all(),
            $rows->pluck('recipient_name_snapshot')->all()
        );
        $this->assertSame(
            [$frozen['version']->id],
            $rows->pluck('profit_scheme_version_id')
                ->unique()
                ->values()
                ->all()
        );
        $this->assertSame(
            [$transaction->id],
            $rows->pluck('transaction_id')->unique()->values()->all()
        );
        $this->assertSame(
            [$item->id],
            $rows->pluck('transaction_item_id')
                ->unique()
                ->values()
                ->all()
        );
        $this->assertSame(
            ['2026-07-23 10:11:12'],
            $rows->map(
                fn (ProfitDistribution $row): string => $row
                    ->created_at
                    ->format('Y-m-d H:i:s')
            )->unique()->values()->all()
        );
        $this->assertFalse(
            array_key_exists(
                'updated_at',
                $rows->firstOrFail()->getAttributes()
            )
        );
        $headerAfter = Arr::except(
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail(),
            ['status', 'failure_reason', 'distribution_started_at',
                'distributed_at', 'updated_at']
        );
        $this->assertSame($headerBefore, $headerAfter);
        $this->assertSame(
            $itemBefore,
            (array) DB::table('transaction_items')
                ->where('id', $item->id)
                ->firstOrFail()
        );
    }

    public function test_difficult_rounding_and_allocation_order_are_preserved(): void
    {
        $frozen = $this->createFrozenVersion([
            '33.3333',
            '33.3333',
            '33.3334',
        ]);
        $transaction = $this->createPendingTransaction([[
            'profit' => '100.00',
            'version' => $frozen['version'],
        ]]);

        $this->distribute($transaction);

        $rows = ProfitDistribution::query()->orderBy('id')->get();
        $this->assertSame(
            collect($frozen['recipients'])->pluck('id')->all(),
            $rows->pluck('profit_recipient_id')->all()
        );
        $this->assertSame(
            ['33.33', '33.33', '33.34'],
            $rows->pluck('amount')->all()
        );
        $this->assertSame(
            '100.00',
            DB::table('profit_distributions')
                ->where('transaction_id', $transaction->id)
                ->sum('amount')
        );
    }

    public function test_multiple_items_use_their_own_frozen_versions_and_may_share_a_recipient(): void
    {
        $shared = $this->createInvestorRecipient();
        $first = $this->createFrozenVersion(
            ['50.0000', '50.0000'],
            recipients: [$shared, $this->createInvestorRecipient()]
        );
        $second = $this->createFrozenVersion(
            ['25.0000', '75.0000'],
            recipients: [$shared, $this->createInvestorRecipient()]
        );
        $transaction = $this->createPendingTransaction([
            ['profit' => '10.00', 'version' => $first['version']],
            ['profit' => '20.00', 'version' => $second['version']],
        ]);
        [$firstItem, $secondItem] = $this->orderedItems($transaction);

        $this->distribute($transaction);

        $this->assertSame(4, ProfitDistribution::query()->count());
        $this->assertSame(2, ProfitDistribution::query()
            ->where('profit_recipient_id', $shared->id)
            ->count());
        $this->assertSame(
            '10.00',
            DB::table('profit_distributions')
                ->where('transaction_item_id', $firstItem->id)
                ->sum('amount')
        );
        $this->assertSame(
            '20.00',
            DB::table('profit_distributions')
                ->where('transaction_item_id', $secondItem->id)
                ->sum('amount')
        );
        $this->assertSame(
            [$first['version']->id],
            ProfitDistribution::query()
                ->where('transaction_item_id', $firstItem->id)
                ->pluck('profit_scheme_version_id')
                ->unique()
                ->values()
                ->all()
        );
        $this->assertSame(
            [$second['version']->id],
            ProfitDistribution::query()
                ->where('transaction_item_id', $secondItem->id)
                ->pluck('profit_scheme_version_id')
                ->unique()
                ->values()
                ->all()
        );
    }

    public function test_current_rule_conditions_and_recipient_activity_do_not_replace_the_frozen_version(): void
    {
        $frozen = $this->createFrozenVersion(['100.0000'], [
            'effective_from' => '2020-01-01',
            'effective_until' => '2020-01-31',
            'priority' => -100,
            'is_active' => false,
        ]);
        $transaction = $this->createPendingTransaction([[
            'profit' => '25.00',
            'version' => $frozen['version'],
        ]]);
        $scheme = $frozen['version']->scheme()->firstOrFail();
        $scheme->update(['is_active' => false]);
        $frozen['recipients'][0]->update([
            'is_active' => false,
            'display_name' => 'Current Inactive Recipient',
        ]);
        $newer = $this->createFrozenVersion(
            ['100.0000'],
            ['version' => 2, 'priority' => 999],
            scheme: $scheme
        );

        $this->distribute($transaction);

        $row = ProfitDistribution::query()->sole();
        $this->assertSame(
            $frozen['version']->id,
            $row->profit_scheme_version_id
        );
        $this->assertNotSame(
            $newer['version']->id,
            $row->profit_scheme_version_id
        );
        $this->assertSame(
            'Current Inactive Recipient',
            $row->recipient_name_snapshot
        );

        $frozen['recipients'][0]->update([
            'display_name' => 'Later Renamed Recipient',
        ]);

        $this->assertSame(
            'Current Inactive Recipient',
            $row->fresh()?->recipient_name_snapshot
        );
    }

    public function test_non_positive_items_are_skipped_even_when_the_overall_transaction_is_negative(): void
    {
        $frozen = $this->createFrozenVersion();
        $transaction = $this->createPendingTransaction([
            ['profit' => '10.00', 'version' => $frozen['version']],
            ['profit' => '0.00'],
            ['profit' => '-100.00'],
        ]);
        [$positive, $breakEven, $loss] = $this->orderedItems(
            $transaction
        );

        $distributed = $this->distribute($transaction);

        $this->assertSame('-90.00', $distributed->total_net_profit);
        $this->assertSame(
            TransactionStatus::Distributed,
            $distributed->status
        );
        $this->assertSame(1, ProfitDistribution::query()->count());
        $this->assertSame(
            [$positive->id],
            ProfitDistribution::query()
                ->pluck('transaction_item_id')
                ->unique()
                ->values()
                ->all()
        );
        $this->assertSame(0, $breakEven->distributions()->count());
        $this->assertSame(0, $loss->distributions()->count());
    }

    public function test_an_entirely_non_profitable_transaction_is_a_successful_zero_row_distribution(): void
    {
        $transaction = $this->createPendingTransaction([
            ['profit' => '0.00'],
            ['profit' => '-10.00'],
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $distributed = $this->distribute($transaction);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame(
            TransactionStatus::Distributed,
            $distributed->status
        );
        $this->assertSame(0, ProfitDistribution::query()->count());
        $sql = strtolower(collect($queries)->pluck('query')->join(' '));
        $this->assertStringNotContainsString(
            'from "profit_scheme_versions"',
            $sql
        );
        $this->assertStringNotContainsString(
            'from "profit_scheme_allocations"',
            $sql
        );
    }

    public function test_zero_cent_allocations_are_omitted_without_changing_the_item_total(): void
    {
        $frozen = $this->createFrozenVersion([
            '50.0000',
            '50.0000',
        ]);
        $transaction = $this->createPendingTransaction([[
            'profit' => '0.01',
            'version' => $frozen['version'],
        ]]);

        $this->distribute($transaction);

        $row = ProfitDistribution::query()->sole();
        $this->assertSame('0.01', $row->amount);
        $this->assertSame('50.0000', $row->percentage);
        $this->assertSame(
            $frozen['recipients'][0]->id,
            $row->profit_recipient_id
        );
    }

    public function test_repeated_distribution_is_an_unchanged_idempotent_no_op(): void
    {
        CarbonImmutable::setTestNow('2026-07-23 10:00:00');
        $frozen = $this->createFrozenVersion();
        $transaction = $this->createPendingTransaction([[
            'profit' => '50.00',
            'version' => $frozen['version'],
        ]]);
        $first = $this->distribute($transaction);
        $firstState = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();
        $rows = DB::table('profit_distributions')
            ->where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
        CarbonImmutable::setTestNow('2026-07-23 11:00:00');

        $second = $this->distribute($transaction);

        $this->assertSame(
            $first->distributed_at?->format('Y-m-d H:i:s'),
            $second->distributed_at?->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            $firstState,
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        $this->assertSame(
            $rows,
            DB::table('profit_distributions')
                ->where('transaction_id', $transaction->id)
                ->orderBy('id')
                ->get()
                ->map(
                    static fn (object $row): array => (array) $row
                )
                ->all()
        );
    }

    public function test_existing_ledger_rows_in_a_pending_state_are_rejected_and_never_deleted(): void
    {
        $frozen = $this->createFrozenVersion();
        $transaction = $this->createPendingTransaction([[
            'profit' => '10.00',
            'version' => $frozen['version'],
        ]]);
        $item = $this->orderedItems($transaction)[0];
        $existing = $this->createDistribution(
            $item,
            $frozen['version'],
            $frozen['recipients'][0],
            ['amount' => '10.00']
        );

        try {
            $this->distribute($transaction);
            $this->fail('Expected inconsistent ledger state.');
        } catch (InvalidProfitDistribution $exception) {
            $this->assertStringContainsString(
                'immutable distribution rows',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $transaction->fresh()?->status
        );
        $this->assertNotNull($existing->fresh());
        $this->assertSame(1, ProfitDistribution::query()->count());
    }

    #[DataProvider('unprocessibleStatuses')]
    public function test_invalid_lifecycle_states_are_rejected(
        TransactionStatus $status
    ): void {
        $transaction = $this->createTransaction(
            creator: $this->actor,
            attributes: ['status' => $status]
        );

        $this->expectException(
            TransactionDistributionNotProcessable::class
        );

        $this->distribute($transaction);
    }

    /**
     * @return array<string, array{TransactionStatus}>
     */
    public static function unprocessibleStatuses(): array
    {
        return [
            'draft' => [TransactionStatus::Draft],
            'cancelled' => [TransactionStatus::Cancelled],
            'failed requires retry action' => [
                TransactionStatus::DistributionFailed,
            ],
        ];
    }

    public function test_a_processing_retry_preserves_the_original_start_time(): void
    {
        $frozen = $this->createFrozenVersion();
        $transaction = $this->createPendingTransaction([[
            'profit' => '10.00',
            'version' => $frozen['version'],
        ]]);
        $transaction->update([
            'status' => TransactionStatus::ProcessingDistribution,
            'distribution_started_at' => '2026-07-23 08:00:00',
        ]);

        $distributed = $this->distribute($transaction);

        $this->assertSame(
            '2026-07-23 08:00:00',
            $distributed->distribution_started_at?->format(
                'Y-m-d H:i:s'
            )
        );
    }

    public function test_missing_unpublished_or_empty_frozen_versions_fail_and_leave_processing_visible(): void
    {
        $missing = $this->createPendingTransaction([[
            'profit' => '10.00',
        ]]);
        $unpublishedVersion = $this->createFrozenVersion();
        $unpublishedVersion['version']->update(['published_at' => null]);
        $unpublished = $this->createPendingTransaction([[
            'profit' => '10.00',
            'version' => $unpublishedVersion['version'],
        ]]);
        $emptyVersion = $this->createFrozenVersion();
        $emptyVersion['version']->allocations()->delete();
        $empty = $this->createPendingTransaction([[
            'profit' => '10.00',
            'version' => $emptyVersion['version'],
        ]]);

        foreach ([
            [$missing, 'missing a valid frozen'],
            [$unpublished, 'unpublished'],
            [$empty, 'has no allocations'],
        ] as [$transaction, $message]) {
            try {
                $this->distribute($transaction);
                $this->fail('Expected invalid frozen rule state.');
            } catch (InvalidProfitDistribution $exception) {
                $this->assertStringContainsString(
                    $message,
                    $exception->getMessage()
                );
            }

            $this->assertSame(
                TransactionStatus::ProcessingDistribution,
                $transaction->fresh()?->status
            );
            $this->assertNotNull(
                $transaction->fresh()?->distribution_started_at
            );
            $this->assertSame(
                0,
                $transaction->distributions()->count()
            );
        }
    }

    public function test_invalid_allocation_total_and_malformed_percentage_are_wrapped_with_item_context(): void
    {
        $invalidTotal = $this->createFrozenVersion([
            '50.0000',
            '50.0000',
        ]);
        $invalidTotal['version']->allocations()
            ->orderBy('id')
            ->firstOrFail()
            ->update(['percentage' => '40.0000']);
        $firstTransaction = $this->createPendingTransaction([[
            'profit' => '10.00',
            'version' => $invalidTotal['version'],
        ]]);

        try {
            $this->distribute($firstTransaction);
            $this->fail('Expected invalid allocation total.');
        } catch (InvalidProfitDistribution $exception) {
            $this->assertStringContainsString(
                'allocation failed',
                $exception->getMessage()
            );
            $this->assertStringContainsString(
                '100.0000',
                $exception->getMessage()
            );
        }

        $malformed = $this->createFrozenVersion();
        $secondTransaction = $this->createPendingTransaction([[
            'profit' => '10.00',
            'version' => $malformed['version'],
        ]]);
        $allocationId = $malformed['version']->allocations()
            ->sole()
            ->id;
        ProfitSchemeAllocation::retrieved(
            static function (
                ProfitSchemeAllocation $allocation
            ) use ($allocationId): void {
                if ($allocation->id !== $allocationId) {
                    return;
                }

                $attributes = $allocation->getAttributes();
                $attributes['percentage'] = 'not-a-percentage';
                $allocation->setRawAttributes($attributes, true);
            }
        );

        try {
            $this->distribute($secondTransaction);
            $this->fail('Expected malformed allocation percentage.');
        } catch (InvalidProfitDistribution $exception) {
            $this->assertStringContainsString(
                'allocation failed',
                $exception->getMessage()
            );
        } finally {
            ProfitSchemeAllocation::flushEventListeners();
        }
    }

    public function test_missing_and_duplicate_allocation_recipients_are_rejected(): void
    {
        $missing = $this->createFrozenVersion();
        $missingTransaction = $this->createPendingTransaction([[
            'profit' => '10.00',
            'version' => $missing['version'],
        ]]);
        $missingAllocationId = $missing['version']->allocations()
            ->sole()
            ->id;
        ProfitSchemeAllocation::retrieved(
            static function (
                ProfitSchemeAllocation $allocation
            ) use ($missingAllocationId): void {
                if ($allocation->id !== $missingAllocationId) {
                    return;
                }

                $attributes = $allocation->getAttributes();
                $attributes['profit_recipient_id'] = 2_147_483_647;
                $allocation->setRawAttributes($attributes, true);
            }
        );

        try {
            $this->distribute($missingTransaction);
            $this->fail('Expected missing allocation recipient.');
        } catch (InvalidProfitDistribution $exception) {
            $this->assertStringContainsString(
                'missing recipient',
                $exception->getMessage()
            );
        } finally {
            ProfitSchemeAllocation::flushEventListeners();
        }

        $duplicate = $this->createFrozenVersion([
            '50.0000',
            '50.0000',
        ]);
        $duplicateTransaction = $this->createPendingTransaction([[
            'profit' => '10.00',
            'version' => $duplicate['version'],
        ]]);
        $allocationIds = $duplicate['version']->allocations()
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $duplicateRecipientId = $duplicate['recipients'][0]->id;
        ProfitSchemeAllocation::retrieved(
            static function (
                ProfitSchemeAllocation $allocation
            ) use ($allocationIds, $duplicateRecipientId): void {
                if (! in_array($allocation->id, $allocationIds, true)) {
                    return;
                }

                $attributes = $allocation->getAttributes();
                $attributes['profit_recipient_id'] = $duplicateRecipientId;
                $allocation->setRawAttributes($attributes, true);
            }
        );

        try {
            $this->distribute($duplicateTransaction);
            $this->fail('Expected duplicate allocation recipients.');
        } catch (InvalidProfitDistribution $exception) {
            $this->assertStringContainsString(
                'must be unique',
                $exception->getMessage()
            );
        } finally {
            ProfitSchemeAllocation::flushEventListeners();
        }
    }

    public function test_database_persistence_failure_rolls_back_every_row_and_final_status(): void
    {
        $frozen = $this->createFrozenVersion([
            '50.0000',
            '50.0000',
        ]);
        $transaction = $this->createPendingTransaction([[
            'profit' => '10.00',
            'version' => $frozen['version'],
        ]]);
        $item = $this->orderedItems($transaction)[0];
        $itemBefore = (array) DB::table('transaction_items')
            ->where('id', $item->id)
            ->firstOrFail();
        $recipientIds = collect($frozen['recipients'])
            ->pluck('id')
            ->all();
        ProfitRecipient::retrieved(
            static function (ProfitRecipient $recipient) use (
                $recipientIds
            ): void {
                if (! in_array($recipient->id, $recipientIds, true)) {
                    return;
                }

                $attributes = $recipient->getAttributes();
                $attributes['display_name'] = str_repeat('X', 161);
                $recipient->setRawAttributes($attributes, true);
            }
        );

        try {
            $this->distribute($transaction);
            $this->fail('Expected PostgreSQL persistence failure.');
        } catch (QueryException) {
        } finally {
            ProfitRecipient::flushEventListeners();
        }

        $fresh = $transaction->fresh();
        $this->assertSame(
            TransactionStatus::ProcessingDistribution,
            $fresh?->status
        );
        $this->assertNotNull($fresh?->distribution_started_at);
        $this->assertNull($fresh?->distributed_at);
        $this->assertSame(0, ProfitDistribution::query()->count());
        $this->assertSame(
            $itemBefore,
            (array) DB::table('transaction_items')
                ->where('id', $item->id)
                ->firstOrFail()
        );
    }

    public function test_distribution_queries_are_bounded_and_use_deterministic_allocation_order(): void
    {
        $frozen = $this->createFrozenVersion([
            '40.0000',
            '30.0000',
            '30.0000',
        ]);
        $transaction = $this->createPendingTransaction(array_fill(
            0,
            5,
            ['profit' => '10.00', 'version' => $frozen['version']]
        ));
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->distribute($transaction);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $selectCounts = collect($queries)
            ->filter(
                static fn (array $query): bool => str_starts_with(
                    strtolower($query['query']),
                    'select'
                )
            );

        foreach ([
            'transaction_items',
            'profit_scheme_versions',
            'profit_scheme_allocations',
            'profit_recipients',
        ] as $table) {
            $this->assertSame(
                1,
                $selectCounts->filter(
                    static fn (array $query): bool => str_contains(
                        strtolower($query['query']),
                        "from \"{$table}\""
                    )
                )->count(),
                "Expected one bounded read from {$table}."
            );
        }

        $allocationQuery = $selectCounts->first(
            static fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "profit_scheme_allocations"'
            )
        );
        $this->assertIsArray($allocationQuery);
        $this->assertStringContainsString(
            'order by "id" asc',
            strtolower($allocationQuery['query'])
        );
        $this->assertSame(15, ProfitDistribution::query()->count());
        $this->assertGreaterThanOrEqual(
            2,
            collect($queries)->filter(
                static fn (array $query): bool => str_contains(
                    strtolower($query['query']),
                    'from "transactions"'
                ) && str_contains(
                    strtolower($query['query']),
                    'for update'
                )
            )->count()
        );
    }
}
