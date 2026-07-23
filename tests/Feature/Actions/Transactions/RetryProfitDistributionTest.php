<?php

namespace Tests\Feature\Actions\Transactions;

use App\Actions\Transactions\RetryProfitDistribution;
use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\ProfitDistributionRetryNotAllowed;
use App\Jobs\DistributeTransactionProfit;
use App\Models\ProfitDistribution;
use App\Services\ProfitSharing\ProfitDistributor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Services\ProfitSharing\ProfitDistributionTestCase;

class RetryProfitDistributionTest extends ProfitDistributionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_failed_transaction_is_reset_and_dispatched_without_changing_frozen_financial_data(): void
    {
        $frozen = $this->createFrozenVersion([
            '60.0000',
            '40.0000',
        ]);
        $transaction = $this->createPendingTransaction([[
            'profit' => '37.25',
            'version' => $frozen['version'],
        ]]);
        $transaction->update([
            'status' => TransactionStatus::DistributionFailed,
            'failure_reason' => 'Transient Redis failure.',
            'distribution_started_at' => '2026-07-23 08:30:00',
        ]);
        $item = $this->orderedItems($transaction)[0];
        $itemBefore = (array) DB::table('transaction_items')
            ->where('id', $item->id)
            ->firstOrFail();
        $preservedHeaderColumns = [
            'total_revenue',
            'total_production_cost',
            'total_net_profit',
            'completed_by_user_id',
            'completed_at',
        ];
        $headerBefore = (array) DB::table('transactions')
            ->select($preservedHeaderColumns)
            ->where('id', $transaction->id)
            ->firstOrFail();

        $pending = app(RetryProfitDistribution::class)->execute(
            $transaction
        );

        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $pending->status
        );
        $this->assertNull($pending->failure_reason);
        $this->assertNull($pending->distribution_started_at);
        $this->assertNull($pending->distributed_at);
        $this->assertSame(
            $headerBefore,
            (array) DB::table('transactions')
                ->select($preservedHeaderColumns)
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        $this->assertSame(
            $itemBefore,
            (array) DB::table('transaction_items')
                ->where('id', $item->id)
                ->firstOrFail()
        );
        $this->assertSame(
            $frozen['version']->id,
            $item->fresh()?->applied_profit_scheme_version_id
        );
        Queue::assertPushedOn(
            'critical',
            DistributeTransactionProfit::class,
            static fn (
                DistributeTransactionProfit $job
            ): bool => $job->transactionId === $transaction->id
                && $job->afterCommit === true
        );
    }

    public function test_pending_redrive_dispatches_without_mutating_the_transaction(): void
    {
        $transaction = $this->createPendingTransaction([[
            'profit' => '0.00',
        ]]);
        $before = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();

        $result = app(RetryProfitDistribution::class)->execute(
            $transaction
        );

        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $result->status
        );
        $this->assertSame(
            $before,
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        Queue::assertPushedOn(
            'critical',
            DistributeTransactionProfit::class,
            static fn (
                DistributeTransactionProfit $job
            ): bool => $job->transactionId === $transaction->id
                && $job->afterCommit === true
        );
    }

    public function test_distributed_retry_is_an_unchanged_no_op(): void
    {
        $transaction = $this->createPendingTransaction([[
            'profit' => '0.00',
        ]]);
        $distributed = $this->distribute($transaction);
        $before = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();

        $result = app(RetryProfitDistribution::class)->execute(
            $distributed
        );

        $this->assertSame(
            TransactionStatus::Distributed,
            $result->status
        );
        $this->assertSame(
            $before,
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        Queue::assertNotPushed(DistributeTransactionProfit::class);
    }

    #[DataProvider('rejectedStatuses')]
    public function test_processing_draft_and_cancelled_retries_are_rejected(
        TransactionStatus $status
    ): void {
        $transaction = $this->createTransaction(
            creator: $this->actor,
            attributes: ['status' => $status]
        );

        try {
            app(RetryProfitDistribution::class)->execute($transaction);
            $this->fail('Expected retry rejection.');
        } catch (ProfitDistributionRetryNotAllowed $exception) {
            $this->assertStringContainsString(
                'cannot',
                $exception->getMessage()
            );
        }

        $this->assertSame($status, $transaction->fresh()?->status);
        Queue::assertNotPushed(DistributeTransactionProfit::class);
    }

    /**
     * @return array<string, array{TransactionStatus}>
     */
    public static function rejectedStatuses(): array
    {
        return [
            'processing' => [
                TransactionStatus::ProcessingDistribution,
            ],
            'draft' => [TransactionStatus::Draft],
            'cancelled' => [TransactionStatus::Cancelled],
        ];
    }

    public function test_failed_retry_with_existing_ledger_rows_is_rejected_without_deleting_history(): void
    {
        $frozen = $this->createFrozenVersion();
        $transaction = $this->createPendingTransaction([[
            'profit' => '10.00',
            'version' => $frozen['version'],
        ]]);
        $transaction->update([
            'status' => TransactionStatus::DistributionFailed,
            'failure_reason' => 'Old failure.',
        ]);
        $item = $this->orderedItems($transaction)[0];
        $row = $this->createDistribution(
            $item,
            $frozen['version'],
            $frozen['recipients'][0],
            ['amount' => '10.00']
        );

        try {
            app(RetryProfitDistribution::class)->execute($transaction);
            $this->fail('Expected immutable-ledger rejection.');
        } catch (ProfitDistributionRetryNotAllowed $exception) {
            $this->assertStringContainsString(
                'immutable ledger rows',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            TransactionStatus::DistributionFailed,
            $transaction->fresh()?->status
        );
        $this->assertSame(
            'Old failure.',
            $transaction->fresh()?->failure_reason
        );
        $this->assertNotNull($row->fresh());
        $this->assertSame(1, ProfitDistribution::query()->count());
        Queue::assertNotPushed(DistributeTransactionProfit::class);
    }

    public function test_successful_retry_and_repeated_redelivery_create_one_ledger_set(): void
    {
        $frozen = $this->createFrozenVersion([
            '50.0000',
            '50.0000',
        ]);
        $transaction = $this->createPendingTransaction([[
            'profit' => '10.00',
            'version' => $frozen['version'],
        ]]);
        $transaction->update([
            'status' => TransactionStatus::DistributionFailed,
            'failure_reason' => 'Retry me.',
        ]);
        $action = app(RetryProfitDistribution::class);
        $pending = $action->execute($transaction);
        $job = new DistributeTransactionProfit((int) $pending->id);
        $distributor = app(ProfitDistributor::class);

        $job->handle($distributor);
        $job->handle($distributor);
        $result = $action->execute($pending->refresh());

        $this->assertSame(
            TransactionStatus::Distributed,
            $result->status
        );
        $this->assertSame(2, ProfitDistribution::query()->count());
        $this->assertSame(
            '10.00',
            DB::table('profit_distributions')
                ->where('transaction_id', $transaction->id)
                ->sum('amount')
        );
        Queue::assertPushed(
            DistributeTransactionProfit::class,
            1
        );
    }
}
