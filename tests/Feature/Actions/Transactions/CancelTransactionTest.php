<?php

namespace Tests\Feature\Actions\Transactions;

use App\Actions\Transactions\CancelTransaction;
use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidTransactionCancellation;
use App\Exceptions\Transactions\TransactionNotCancellable;
use App\Exceptions\Transactions\TransactionNotCompletable;
use App\Jobs\DistributeTransactionProfit;
use App\Models\ProfitDistribution;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;

class CancelTransactionTest extends TransactionActionTestCase
{
    public function test_draft_is_cancelled_under_parent_lock_without_mutating_history_or_financial_data(): void
    {
        CarbonImmutable::setTestNow('2026-07-24 14:15:16');
        $category = $this->createCategory([
            'name' => 'Original Category',
        ]);
        $firstProduct = $this->createProduct($category, [
            'name' => 'Original First Product',
            'sku' => 'ORIGINAL-FIRST',
            'default_selling_price' => '180.25',
            'default_production_cost' => '70.10',
        ]);
        $secondProduct = $this->createProduct($category, [
            'name' => 'Original Second Product',
            'sku' => 'ORIGINAL-SECOND',
            'default_selling_price' => '90.00',
            'default_production_cost' => '40.00',
        ]);
        $transaction = $this->createDraft(
            $this->validPayload(items: [
                ['product_id' => $firstProduct->id, 'quantity' => 2],
                ['product_id' => $secondProduct->id, 'quantity' => 3],
            ])
        );
        $preservedHeaderFields = [
            'transaction_number',
            'customer_id',
            'transaction_date',
            'total_revenue',
            'total_production_cost',
            'total_net_profit',
            'created_by_user_id',
            'completed_by_user_id',
            'completed_at',
            'distribution_started_at',
            'distributed_at',
        ];
        $headerBefore = (array) DB::table('transactions')
            ->select($preservedHeaderFields)
            ->where('id', $transaction->id)
            ->firstOrFail();
        $itemsBefore = DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $cancelled = app(CancelTransaction::class)
                ->execute($transaction);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            CarbonImmutable::setTestNow();
        }

        $this->assertSame(
            TransactionStatus::Cancelled,
            $cancelled->status
        );
        $this->assertSame(
            '2026-07-24 14:15:16',
            $cancelled->cancelled_at?->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            $headerBefore,
            (array) DB::table('transactions')
                ->select($preservedHeaderFields)
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        $this->assertSame(
            $transaction->customer_id,
            $cancelled->customer()->firstOrFail()->id
        );
        $this->assertSame(
            $itemsBefore,
            DB::table('transaction_items')
                ->where('transaction_id', $transaction->id)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all()
        );
        $this->assertTrue($this->usedParentRowLock($queries));
        $sql = strtolower(collect($queries)->pluck('query')->join(' '));
        $this->assertStringNotContainsString(
            'profit_scheme_versions',
            $sql
        );
        $this->assertSame(
            [null, null],
            $cancelled->items()
                ->orderBy('id')
                ->pluck('applied_profit_scheme_version_id')
                ->all()
        );
        $this->assertSame(
            [null, null],
            $cancelled->items()
                ->orderBy('id')
                ->pluck('rule_resolved_at')
                ->all()
        );
        $this->assertSame(0, ProfitDistribution::query()->count());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        Queue::assertNothingPushed();
    }

    public function test_draft_with_frozen_rule_data_is_rejected_without_mutation(): void
    {
        $transaction = $this->createDraft($this->validPayload());
        $version = $this->createPublishedRule(
            $this->createCategory()
        );
        $item = $transaction->items()->sole();
        $item->update([
            'applied_profit_scheme_version_id' => $version->id,
            'rule_resolved_at' => now(),
        ]);
        $headerBefore = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();
        $itemBefore = (array) DB::table('transaction_items')
            ->where('id', $item->id)
            ->firstOrFail();

        try {
            app(CancelTransaction::class)->execute($transaction);
            $this->fail(
                'Expected a draft with frozen rule data to be rejected.'
            );
        } catch (InvalidTransactionCancellation $exception) {
            $this->assertStringContainsString(
                'profit-sharing data',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            $headerBefore,
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        $this->assertSame(
            $itemBefore,
            (array) DB::table('transaction_items')
                ->where('id', $item->id)
                ->firstOrFail()
        );
        Queue::assertNothingPushed();
    }

    public function test_draft_with_distribution_rows_is_rejected_without_mutation(): void
    {
        $transaction = $this->createDraft($this->validPayload());
        $item = $transaction->items()->sole();
        $version = $this->createPublishedRule(
            $this->createCategory()
        );
        $distribution = $this->createDistribution(
            $item,
            $version,
            $this->createCompanyRecipient()
        );
        $headerBefore = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();
        $distributionBefore = (array) DB::table(
            'profit_distributions'
        )
            ->where('id', $distribution->id)
            ->firstOrFail();

        try {
            app(CancelTransaction::class)->execute($transaction);
            $this->fail(
                'Expected a draft with distribution rows to be rejected.'
            );
        } catch (InvalidTransactionCancellation $exception) {
            $this->assertStringContainsString(
                'profit distributions',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            $headerBefore,
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        $this->assertSame(
            $distributionBefore,
            (array) DB::table('profit_distributions')
                ->where('id', $distribution->id)
                ->firstOrFail()
        );
        Queue::assertNothingPushed();
    }

    public function test_repeated_cancellation_is_an_unchanged_idempotent_no_op(): void
    {
        CarbonImmutable::setTestNow('2026-07-24 10:00:00');
        $transaction = $this->createDraft($this->validPayload());
        $firstResult = app(CancelTransaction::class)
            ->execute($transaction);
        $originalCancelledAt = $firstResult->cancelled_at?->format(
            'Y-m-d H:i:s.u'
        );
        $rowBefore = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();

        CarbonImmutable::setTestNow('2026-07-24 12:00:00');
        $secondResult = app(CancelTransaction::class)
            ->execute($transaction);
        CarbonImmutable::setTestNow();

        $this->assertSame(
            TransactionStatus::Cancelled,
            $secondResult->status
        );
        $this->assertSame(
            $originalCancelledAt,
            $secondResult->cancelled_at?->format('Y-m-d H:i:s.u')
        );
        $this->assertSame(
            $rowBefore,
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        Queue::assertNothingPushed();
    }

    #[DataProvider('finalizedStatuses')]
    public function test_non_draft_non_cancelled_status_is_rejected_without_mutation(
        TransactionStatus $status
    ): void {
        $transaction = $this->createDraft($this->validPayload());
        $transaction->update(['status' => $status]);
        $headerBefore = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();
        $itemsBefore = DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        try {
            app(CancelTransaction::class)->execute($transaction);
            $this->fail(
                'Expected a finalized transaction cancellation to fail.'
            );
        } catch (TransactionNotCancellable $exception) {
            $this->assertStringContainsString(
                $status->value,
                $exception->getMessage()
            );
        }

        $this->assertSame(
            $headerBefore,
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        $this->assertSame(
            $itemsBefore,
            DB::table('transaction_items')
                ->where('transaction_id', $transaction->id)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all()
        );
        Queue::assertNothingPushed();
    }

    /**
     * @return array<string, array{TransactionStatus}>
     */
    public static function finalizedStatuses(): array
    {
        return [
            'pending distribution' => [
                TransactionStatus::PendingDistribution,
            ],
            'processing distribution' => [
                TransactionStatus::ProcessingDistribution,
            ],
            'distributed' => [TransactionStatus::Distributed],
            'distribution failed' => [
                TransactionStatus::DistributionFailed,
            ],
        ];
    }

    public function test_cancel_first_prevents_completion_and_queue_dispatch(): void
    {
        $category = $this->createCategory();
        $product = $this->createProduct($category, [
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $this->createPublishedRule($category);
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );

        app(CancelTransaction::class)->execute($transaction);

        try {
            $this->complete($transaction);
            $this->fail(
                'Expected completion after cancellation to fail.'
            );
        } catch (TransactionNotCompletable $exception) {
            $this->assertStringContainsString(
                TransactionStatus::Cancelled->value,
                $exception->getMessage()
            );
        }

        $fresh = $transaction->fresh();
        $this->assertSame(
            TransactionStatus::Cancelled,
            $fresh?->status
        );
        $this->assertNull($fresh?->completed_at);
        $this->assertNull($fresh?->completed_by_user_id);
        $this->assertNull($fresh?->distribution_started_at);
        $this->assertNull($fresh?->distributed_at);
        $this->assertNull(
            $transaction->items()
                ->sole()
                ->applied_profit_scheme_version_id
        );
        $this->assertNull(
            $transaction->items()->sole()->rule_resolved_at
        );
        $this->assertSame(0, $transaction->distributions()->count());
        Queue::assertNotPushed(DistributeTransactionProfit::class);
    }

    public function test_complete_first_prevents_cancellation_and_proceeds_normally(): void
    {
        $category = $this->createCategory();
        $product = $this->createProduct($category, [
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $version = $this->createPublishedRule($category);
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );
        $completed = $this->complete($transaction);
        $headerBefore = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();
        $itemBefore = (array) DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->firstOrFail();

        try {
            app(CancelTransaction::class)->execute($transaction);
            $this->fail(
                'Expected cancellation after completion to fail.'
            );
        } catch (TransactionNotCancellable $exception) {
            $this->assertStringContainsString(
                TransactionStatus::PendingDistribution->value,
                $exception->getMessage()
            );
        }

        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $completed->fresh()?->status
        );
        $this->assertSame(
            $headerBefore,
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        $this->assertSame(
            $itemBefore,
            (array) DB::table('transaction_items')
                ->where('transaction_id', $transaction->id)
                ->firstOrFail()
        );
        $this->assertSame(
            $version->id,
            $transaction->items()
                ->sole()
                ->applied_profit_scheme_version_id
        );
        $this->assertNull($completed->fresh()?->cancelled_at);
        Queue::assertPushed(DistributeTransactionProfit::class, 1);
    }

    public function test_unsaved_transaction_is_rejected(): void
    {
        $this->expectException(
            InvalidTransactionCancellation::class
        );
        $this->expectExceptionMessage('must be persisted');

        app(CancelTransaction::class)->execute(new Transaction);
    }

    /**
     * @param  list<array{
     *     query: string,
     *     bindings: array<int, mixed>,
     *     time: float
     * }>  $queries
     */
    private function usedParentRowLock(array $queries): bool
    {
        return collect($queries)->contains(
            static fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "transactions"'
            ) && str_contains(
                strtolower($query['query']),
                'for update'
            )
        );
    }
}
