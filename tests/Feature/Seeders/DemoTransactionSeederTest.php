<?php

namespace Tests\Feature\Seeders;

use App\Actions\Transactions\CompleteTransaction;
use App\Actions\Transactions\RetryProfitDistribution;
use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\ProfitRuleNotFoundForTransactionItem;
use App\Jobs\DistributeTransactionProfit;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\ProfitSharing\FixedDecimal;
use App\Services\ProfitSharing\ProfitCalculator;
use App\Services\ProfitSharing\ProfitRuleResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DemoTransactionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_contains_all_stable_lifecycle_scenarios(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(12, Transaction::query()->count());
        $this->assertSame(
            array_map(
                static fn (int $number): string => sprintf(
                    'TRX-DEMO-%03d',
                    $number
                ),
                range(1, 12)
            ),
            Transaction::query()
                ->orderBy('transaction_number')
                ->pluck('transaction_number')
                ->all()
        );
        $this->assertSame(
            12,
            Transaction::query()
                ->distinct('transaction_number')
                ->count('transaction_number')
        );

        $this->assertOnlyAppliedVersion(
            'TRX-DEMO-001',
            'electronics-growth',
            1
        );
        $this->assertOnlyAppliedVersion(
            'TRX-DEMO-002',
            'electronics-growth',
            2
        );
        $this->assertOnlyAppliedVersion(
            'TRX-DEMO-003',
            'home-office-growth',
            1
        );
        $this->assertOnlyAppliedVersion(
            'TRX-DEMO-006',
            'general-profit-sharing',
            1
        );
    }

    public function test_mixed_all_loss_and_general_fallback_history_is_coherent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mixed = $this->transaction('TRX-DEMO-004');
        $positive = $mixed->items
            ->firstWhere('product_sku_snapshot', 'ELEC-HEADPHONES-01');
        $loss = $mixed->items
            ->firstWhere('product_sku_snapshot', 'OFFICE-CHAIR-01');

        $this->assertInstanceOf(TransactionItem::class, $positive);
        $this->assertInstanceOf(TransactionItem::class, $loss);
        $this->assertSame(TransactionStatus::Distributed, $mixed->status);
        $this->assertSame('900000.00', $mixed->total_net_profit);
        $this->assertSame('1400000.00', $positive->net_profit);
        $this->assertSame('-500000.00', $loss->net_profit);
        $this->assertSame(
            '1400000.00',
            $mixed->distributions()->sum('amount')
        );
        $this->assertSame(3, $positive->distributions()->count());
        $this->assertSame(0, $loss->distributions()->count());
        $this->assertNotSame(
            $mixed->total_net_profit,
            $mixed->distributions()->sum('amount')
        );

        $allLoss = $this->transaction('TRX-DEMO-005');

        $this->assertSame(TransactionStatus::Distributed, $allLoss->status);
        $this->assertSame('-310000.00', $allLoss->total_net_profit);
        $this->assertSame(0, $allLoss->distributions()->count());
        $this->assertTrue(
            $allLoss->items->every(
                fn (TransactionItem $item): bool => FixedDecimal::moneyToMinorUnits(
                    $item->net_profit
                ) <= 0
            )
        );

        $fallback = $this->transaction('TRX-DEMO-006');
        $fallbackVersion = $fallback->items
            ->sole()
            ->appliedProfitSchemeVersion;

        $this->assertNotNull($fallbackVersion);
        $this->assertSame(
            'general-profit-sharing',
            $fallbackVersion->scheme->code
        );
        $this->assertSame(
            $fallback->items->sole()->net_profit,
            $fallback->distributions()->sum('amount')
        );
    }

    public function test_draft_pending_failed_and_cancelled_fixtures_are_actionable(): void
    {
        $this->seed(DatabaseSeeder::class);

        $draft = $this->transaction('TRX-DEMO-008');
        $missingRule = $this->transaction('TRX-DEMO-009');
        $pending = $this->transaction('TRX-DEMO-010');
        $failed = $this->transaction('TRX-DEMO-011');
        $cancelled = $this->transaction('TRX-DEMO-012');

        foreach ([$draft, $missingRule] as $transaction) {
            $this->assertSame(TransactionStatus::Draft, $transaction->status);
            $this->assertNull($transaction->completed_at);
            $this->assertSame(0, $transaction->distributions()->count());
            $this->assertTrue(
                $transaction->items->every(
                    fn (TransactionItem $item): bool => $item
                        ->applied_profit_scheme_version_id === null
                        && $item->rule_resolved_at === null
                )
            );
        }

        $missingRuleItem = $missingRule->items->sole();
        $this->assertGreaterThan(
            0,
            FixedDecimal::moneyToMinorUnits($missingRuleItem->net_profit)
        );
        $this->assertNull(
            app(ProfitRuleResolver::class)->resolve(
                $missingRule->transaction_date,
                $missingRuleItem->product_category_id,
                $missingRuleItem->net_profit
            )
        );

        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $pending->status
        );
        $this->assertNotNull($pending->completed_by_user_id);
        $this->assertNotNull($pending->completed_at);
        $this->assertNull($pending->distribution_started_at);
        $this->assertSame(0, $pending->distributions()->count());
        $this->assertFinalizedPositiveItemsAreFrozen($pending);

        $this->assertSame(
            TransactionStatus::DistributionFailed,
            $failed->status
        );
        $this->assertNotNull($failed->completed_by_user_id);
        $this->assertNotNull($failed->completed_at);
        $this->assertNotNull($failed->distribution_started_at);
        $this->assertNull($failed->distributed_at);
        $this->assertSame(
            'Profit distribution failed: Profit distribution could not be completed after the configured retries.',
            $failed->failure_reason
        );
        $this->assertSame(0, $failed->distributions()->count());
        $this->assertFinalizedPositiveItemsAreFrozen($failed);

        $this->assertSame(TransactionStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertNull($cancelled->completed_at);
        $this->assertNull($cancelled->completed_by_user_id);
        $this->assertSame(0, $cancelled->distributions()->count());
        $this->assertTrue(
            $cancelled->items->every(
                fn (TransactionItem $item): bool => $item
                    ->applied_profit_scheme_version_id === null
            )
        );
    }

    public function test_all_seeded_financial_snapshots_match_production_calculators_and_allocations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $calculator = app(ProfitCalculator::class);

        foreach (Transaction::query()->with('items')->get() as $transaction) {
            $calculations = [];

            foreach ($transaction->items as $item) {
                $calculation = $calculator->calculateItem(
                    $item->quantity,
                    $item->unit_selling_price,
                    $item->unit_production_cost
                );
                $calculations[] = $calculation;

                $this->assertSame(
                    $calculation->totalRevenue,
                    $item->total_revenue
                );
                $this->assertSame(
                    $calculation->totalProductionCost,
                    $item->total_production_cost
                );
                $this->assertSame(
                    $calculation->netProfit,
                    $item->net_profit
                );

                if (FixedDecimal::moneyToMinorUnits($item->net_profit) <= 0) {
                    $this->assertSame(0, $item->distributions()->count());
                }
            }

            $totals = $calculator->aggregateItems($calculations);

            $this->assertSame(
                $totals->totalRevenue,
                $transaction->total_revenue
            );
            $this->assertSame(
                $totals->totalProductionCost,
                $transaction->total_production_cost
            );
            $this->assertSame(
                $totals->totalNetProfit,
                $transaction->total_net_profit
            );
        }

        foreach (Transaction::query()
            ->where('status', TransactionStatus::Distributed->value)
            ->with('items.distributions.recipient')
            ->get() as $transaction) {
            foreach ($transaction->items as $item) {
                if (FixedDecimal::moneyToMinorUnits($item->net_profit) <= 0) {
                    continue;
                }

                $this->assertSame(
                    $item->net_profit,
                    $item->distributions()->sum('amount')
                );

                foreach ($item->distributions as $distribution) {
                    $allocation = DB::table('profit_scheme_allocations')
                        ->where(
                            'profit_scheme_version_id',
                            $distribution->profit_scheme_version_id
                        )
                        ->where(
                            'profit_recipient_id',
                            $distribution->profit_recipient_id
                        )
                        ->first();

                    $this->assertNotNull($allocation);
                    $this->assertSame(
                        $allocation->percentage,
                        $distribution->percentage
                    );
                    $this->assertNotSame(
                        '',
                        $distribution->recipient_name_snapshot
                    );
                    $this->assertSame(
                        $distribution->recipient?->display_name,
                        $distribution->recipient_name_snapshot
                    );
                }
            }
        }
    }

    public function test_missing_rule_draft_demonstrates_completion_rejection_without_mutation(): void
    {
        Queue::fake();
        $this->seed(DatabaseSeeder::class);

        $transaction = $this->transaction('TRX-DEMO-009');
        $before = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();

        try {
            app(CompleteTransaction::class)->execute(
                $transaction,
                User::query()->where('email', 'admin@example.com')->sole()
            );
            $this->fail('Expected the missing-rule fixture to reject completion.');
        } catch (ProfitRuleNotFoundForTransactionItem) {
            $this->assertSame(
                $before,
                (array) DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->firstOrFail()
            );
        }

        Queue::assertNothingPushed();
    }

    public function test_pending_and_failed_fixtures_use_existing_redrive_and_retry_flows(): void
    {
        Queue::fake();
        $this->seed(DatabaseSeeder::class);
        Queue::assertNothingPushed();

        $pending = $this->transaction('TRX-DEMO-010');
        $failed = $this->transaction('TRX-DEMO-011');
        $pendingItems = $this->rawItems($pending);
        $failedItems = $this->rawItems($failed);
        $action = app(RetryProfitDistribution::class);

        $redriven = $action->execute($pending);
        $retried = $action->execute($failed);

        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $redriven->status
        );
        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $retried->status
        );
        $this->assertNull($retried->failure_reason);
        $this->assertNull($retried->distribution_started_at);
        $this->assertSame($pendingItems, $this->rawItems($pending));
        $this->assertSame($failedItems, $this->rawItems($failed));
        Queue::assertPushed(
            DistributeTransactionProfit::class,
            2
        );
        Queue::assertPushed(
            DistributeTransactionProfit::class,
            fn (DistributeTransactionProfit $job): bool => $job
                ->transactionId === $pending->id
        );
        Queue::assertPushed(
            DistributeTransactionProfit::class,
            fn (DistributeTransactionProfit $job): bool => $job
                ->transactionId === $failed->id
        );
    }

    private function assertOnlyAppliedVersion(
        string $transactionNumber,
        string $schemeCode,
        int $versionNumber
    ): void {
        $transaction = $this->transaction($transactionNumber);
        $version = ProfitSchemeVersion::query()
            ->whereRelation('scheme', 'code', $schemeCode)
            ->where('version', $versionNumber)
            ->sole();

        $this->assertSame(
            [$version->id],
            $transaction->items
                ->pluck('applied_profit_scheme_version_id')
                ->unique()
                ->values()
                ->all()
        );
    }

    private function assertFinalizedPositiveItemsAreFrozen(
        Transaction $transaction
    ): void {
        foreach ($transaction->items as $item) {
            if (FixedDecimal::moneyToMinorUnits($item->net_profit) > 0) {
                $this->assertNotNull(
                    $item->applied_profit_scheme_version_id
                );
                $this->assertNotNull($item->rule_resolved_at);
            }
        }
    }

    private function transaction(string $number): Transaction
    {
        return Transaction::query()
            ->with([
                'items.appliedProfitSchemeVersion.scheme',
                'items.distributions',
            ])
            ->where('transaction_number', $number)
            ->sole();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rawItems(Transaction $transaction): array
    {
        return DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }
}
