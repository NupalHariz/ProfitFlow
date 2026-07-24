<?php

namespace Tests\Feature\Actions\Transactions;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidTransactionCompletion;
use App\Exceptions\Transactions\TransactionNotCompletable;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class CompleteTransactionLifecycleTest extends TransactionActionTestCase
{
    #[DataProvider('nonDraftStatuses')]
    public function test_only_draft_transactions_can_be_completed(
        TransactionStatus $status
    ): void {
        $product = $this->createProduct();
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );
        $transaction->update(['status' => $status]);
        $before = DB::table('transactions')
            ->where('id', $transaction->id)
            ->first();

        try {
            $this->complete($transaction);
            $this->fail(
                'Expected a non-draft completion to be rejected.'
            );
        } catch (TransactionNotCompletable $exception) {
            $this->assertStringContainsString(
                $status->value,
                $exception->getMessage()
            );
        }

        $this->assertEquals(
            $before,
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->first()
        );
    }

    /**
     * @return array<string, array{TransactionStatus}>
     */
    public static function nonDraftStatuses(): array
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
            'cancelled' => [TransactionStatus::Cancelled],
        ];
    }

    public function test_repeated_completion_is_rejected_instead_of_being_an_idempotent_no_op(): void
    {
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $this->createPublishedRule(
            $product->category()->firstOrFail()
        );
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );
        $completed = $this->complete($transaction);
        $completedAt = $completed->completed_at?->format(
            'Y-m-d H:i:s.u'
        );
        $ruleId = $completed->items
            ->sole()
            ->applied_profit_scheme_version_id;

        try {
            $this->complete($completed);
            $this->fail('Expected repeated completion to fail.');
        } catch (TransactionNotCompletable $exception) {
            $this->assertStringContainsString(
                TransactionStatus::PendingDistribution->value,
                $exception->getMessage()
            );
        }

        $fresh = $completed->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(
            $completedAt,
            $fresh->completed_at?->format('Y-m-d H:i:s.u')
        );
        $this->assertSame(
            $ruleId,
            $fresh->items()->firstOrFail()
                ->applied_profit_scheme_version_id
        );
    }

    public function test_an_unsaved_transaction_is_rejected(): void
    {
        $transaction = new Transaction;

        $this->expectException(InvalidTransactionCompletion::class);
        $this->expectExceptionMessage('must be persisted');

        $this->complete($transaction);
    }

    public function test_an_unsaved_completer_is_rejected_after_the_parent_lock(): void
    {
        $product = $this->createProduct();
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );
        $before = DB::table('transactions')
            ->where('id', $transaction->id)
            ->first();
        $completer = new User([
            'name' => 'Unsaved Completer',
            'email' => 'unsaved@example.com',
            'password' => 'password',
        ]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->complete($transaction, $completer);
            $this->fail('Expected unsaved completer to fail.');
        } catch (InvalidTransactionCompletion $exception) {
            $this->assertStringContainsString(
                'persisted user',
                $exception->getMessage()
            );
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $this->assertTrue($this->usedParentRowLock($queries));
        $this->assertEquals(
            $before,
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->first()
        );
    }

    public function test_completion_acquires_the_parent_row_lock_before_loading_items(): void
    {
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '100.00',
            'default_production_cost' => '100.00',
        ]);
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->complete($transaction);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $this->assertTrue($this->usedParentRowLock($queries));
        $lockIndex = collect($queries)->search(
            static fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "transactions"'
            ) && str_contains(
                strtolower($query['query']),
                'for update'
            )
        );
        $itemsIndex = collect($queries)->search(
            static fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "transaction_items"'
            )
        );

        $this->assertIsInt($lockIndex);
        $this->assertIsInt($itemsIndex);
        $this->assertLessThan($itemsIndex, $lockIndex);
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
