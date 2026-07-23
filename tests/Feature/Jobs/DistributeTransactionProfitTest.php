<?php

namespace Tests\Feature\Jobs;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidProfitDistribution;
use App\Exceptions\Transactions\TransactionDistributionNotProcessable;
use App\Jobs\DistributeTransactionProfit;
use App\Models\ProfitDistribution;
use App\Services\ProfitSharing\ProfitDistributor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use RuntimeException;
use Tests\Feature\Services\ProfitSharing\ProfitDistributionTestCase;

class DistributeTransactionProfitTest extends ProfitDistributionTestCase
{
    public function test_job_configuration_is_bounded_and_payload_is_id_only(): void
    {
        $job = new DistributeTransactionProfit(123);
        $serialized = serialize($job);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(123, $job->transactionId);
        $this->assertSame('critical', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff());
        $this->assertSame(120, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertStringNotContainsString(
            'App\\Models\\Transaction',
            $serialized
        );
    }

    public function test_handle_delegates_to_the_distributor_and_duplicate_delivery_is_a_no_op(): void
    {
        $frozen = $this->createFrozenVersion([
            '40.0000',
            '30.0000',
            '30.0000',
        ]);
        $transaction = $this->createPendingTransaction([[
            'profit' => '100.00',
            'version' => $frozen['version'],
        ]]);
        $job = new DistributeTransactionProfit((int) $transaction->id);
        $distributor = app(ProfitDistributor::class);

        $job->handle($distributor);
        $firstState = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();
        $firstRows = DB::table('profit_distributions')
            ->where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        $job->handle($distributor);

        $this->assertSame(
            TransactionStatus::Distributed,
            $transaction->fresh()?->status
        );
        $this->assertSame(
            ['40.00', '30.00', '30.00'],
            ProfitDistribution::query()
                ->orderBy('id')
                ->pluck('amount')
                ->all()
        );
        $this->assertSame(
            $firstState,
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        $this->assertSame(
            $firstRows,
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

    public function test_handle_does_not_suppress_distributor_exceptions(): void
    {
        $draft = $this->createTransaction(
            creator: $this->actor,
            attributes: ['status' => TransactionStatus::Draft]
        );
        $job = new DistributeTransactionProfit((int) $draft->id);

        $this->expectException(
            TransactionDistributionNotProcessable::class
        );

        $job->handle(app(ProfitDistributor::class));
    }

    public function test_failed_callback_records_a_sanitized_final_failure_without_mutating_snapshots(): void
    {
        $frozen = $this->createFrozenVersion();
        $transaction = $this->createPendingTransaction([[
            'profit' => '12.34',
            'version' => $frozen['version'],
        ]]);
        $transaction->update([
            'status' => TransactionStatus::ProcessingDistribution,
            'distribution_started_at' => '2026-07-23 08:30:00',
            'failure_reason' => 'older failure',
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
        $exception = new InvalidProfitDistribution(
            '<b>Redis unavailable</b>'."\n"
            .'token=super-secret '.str_repeat('detail ', 300)
        );

        (new DistributeTransactionProfit(
            (int) $transaction->id
        ))->failed($exception);

        $failed = $transaction->fresh();
        $this->assertSame(
            TransactionStatus::DistributionFailed,
            $failed?->status
        );
        $this->assertSame(
            '2026-07-23 08:30:00',
            $failed?->distribution_started_at?->format(
                'Y-m-d H:i:s'
            )
        );
        $this->assertNull($failed?->distributed_at);
        $this->assertIsString($failed?->failure_reason);
        $this->assertStringContainsString(
            'Redis unavailable',
            $failed->failure_reason
        );
        $this->assertStringContainsString(
            'token=[redacted]',
            $failed->failure_reason
        );
        $this->assertStringNotContainsString(
            'super-secret',
            $failed->failure_reason
        );
        $this->assertStringNotContainsString(
            '<b>',
            $failed->failure_reason
        );
        $this->assertLessThanOrEqual(
            1000,
            Str::length($failed->failure_reason)
        );
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
    }

    public function test_failed_callback_may_mark_pending_but_never_downgrades_distributed(): void
    {
        $pending = $this->createPendingTransaction([[
            'profit' => '0.00',
        ]]);

        (new DistributeTransactionProfit(
            (int) $pending->id
        ))->failed(new InvalidProfitDistribution('Frozen rule missing.'));

        $this->assertSame(
            TransactionStatus::DistributionFailed,
            $pending->fresh()?->status
        );
        $this->assertStringContainsString(
            'Frozen rule missing.',
            (string) $pending->fresh()?->failure_reason
        );

        $distributed = $this->createPendingTransaction([[
            'profit' => '0.00',
        ]]);
        $this->distribute($distributed);
        $before = (array) DB::table('transactions')
            ->where('id', $distributed->id)
            ->firstOrFail();

        (new DistributeTransactionProfit(
            (int) $distributed->id
        ))->failed(new RuntimeException('Late worker failure.'));

        $this->assertSame(
            $before,
            (array) DB::table('transactions')
                ->where('id', $distributed->id)
                ->firstOrFail()
        );
    }

    public function test_failed_callback_does_not_store_sql_bindings_or_database_credentials(): void
    {
        $transaction = $this->createPendingTransaction([[
            'profit' => '0.00',
        ]]);
        $transaction->update([
            'status' => TransactionStatus::ProcessingDistribution,
        ]);
        $databaseException = new QueryException(
            'pgsql',
            'insert into secret_table (token) values (?)',
            ['super-secret-binding'],
            new PDOException('password=hunter2')
        );

        (new DistributeTransactionProfit(
            (int) $transaction->id
        ))->failed($databaseException);

        $reason = (string) $transaction->fresh()?->failure_reason;
        $this->assertSame(
            'Profit distribution failed: A database error prevented profit distribution.',
            $reason
        );
        $this->assertStringNotContainsString(
            'secret_table',
            $reason
        );
        $this->assertStringNotContainsString(
            'super-secret-binding',
            $reason
        );
        $this->assertStringNotContainsString('hunter2', $reason);
    }

    public function test_failed_callback_leaves_draft_cancelled_and_existing_ledger_rows_unchanged(): void
    {
        $frozen = $this->createFrozenVersion();
        $draft = $this->createTransaction(
            creator: $this->actor,
            attributes: [
                'status' => TransactionStatus::Draft,
                'failure_reason' => 'draft reason',
            ]
        );
        $draftItem = $this->createTransactionItem(
            $draft,
            version: $frozen['version']
        );
        $row = $this->createDistribution(
            $draftItem,
            $frozen['version'],
            $frozen['recipients'][0]
        );
        $cancelled = $this->createTransaction(
            creator: $this->actor,
            attributes: [
                'status' => TransactionStatus::Cancelled,
                'failure_reason' => 'cancelled reason',
            ]
        );
        $draftBefore = (array) DB::table('transactions')
            ->where('id', $draft->id)
            ->firstOrFail();
        $cancelledBefore = (array) DB::table('transactions')
            ->where('id', $cancelled->id)
            ->firstOrFail();

        (new DistributeTransactionProfit(
            (int) $draft->id
        ))->failed(new RuntimeException('Should be ignored.'));
        (new DistributeTransactionProfit(
            (int) $cancelled->id
        ))->failed(new RuntimeException('Should be ignored.'));

        $this->assertSame(
            $draftBefore,
            (array) DB::table('transactions')
                ->where('id', $draft->id)
                ->firstOrFail()
        );
        $this->assertSame(
            $cancelledBefore,
            (array) DB::table('transactions')
                ->where('id', $cancelled->id)
                ->firstOrFail()
        );
        $this->assertNotNull($row->fresh());
        $this->assertSame(1, ProfitDistribution::query()->count());
    }
}
