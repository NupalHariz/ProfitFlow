<?php

namespace Tests\Feature\Actions\Transactions;

use App\Actions\Transactions\RetryProfitDistribution;
use App\Enums\TransactionStatus;
use App\Jobs\DistributeTransactionProfit;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Services\ProfitSharing\ProfitDistributionTestCase;
use Throwable;

class RetryProfitDistributionDispatchTest extends ProfitDistributionTestCase
{
    public function test_retry_dispatch_waits_for_the_outermost_commit(): void
    {
        config(['queue.default' => 'database']);
        $transaction = $this->createPendingTransaction([[
            'profit' => '0.00',
        ]]);
        $transaction->update([
            'status' => TransactionStatus::DistributionFailed,
            'failure_reason' => 'Retry me.',
            'distribution_started_at' => '2026-07-23 08:00:00',
        ]);
        DB::beginTransaction();

        try {
            $pending = app(RetryProfitDistribution::class)->execute(
                $transaction
            );

            $this->assertSame(0, DB::table('jobs')->count());
            DB::commit();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 1) {
                DB::rollBack();
            }

            throw $exception;
        }

        $queued = DB::table('jobs')->sole();
        $payload = json_decode($queued->payload, true);
        $this->assertIsArray($payload);
        $command = unserialize($payload['data']['command']);

        $this->assertInstanceOf(
            DistributeTransactionProfit::class,
            $command
        );
        $this->assertSame($pending->id, $command->transactionId);
        $this->assertSame('critical', $queued->queue);
        $this->assertTrue($command->afterCommit);
    }

    public function test_outer_retry_rollback_restores_failure_and_never_dispatches(): void
    {
        config(['queue.default' => 'database']);
        $transaction = $this->createPendingTransaction([[
            'profit' => '0.00',
        ]]);
        $transaction->update([
            'status' => TransactionStatus::DistributionFailed,
            'failure_reason' => 'Retry me.',
            'distribution_started_at' => '2026-07-23 08:00:00',
        ]);
        DB::beginTransaction();

        app(RetryProfitDistribution::class)->execute($transaction);

        $this->assertSame(0, DB::table('jobs')->count());
        DB::rollBack();

        $fresh = $transaction->fresh();
        $this->assertSame(
            TransactionStatus::DistributionFailed,
            $fresh?->status
        );
        $this->assertSame('Retry me.', $fresh?->failure_reason);
        $this->assertSame(
            '2026-07-23 08:00:00',
            $fresh?->distribution_started_at?->format(
                'Y-m-d H:i:s'
            )
        );
        $this->assertSame(0, DB::table('jobs')->count());
    }
}
