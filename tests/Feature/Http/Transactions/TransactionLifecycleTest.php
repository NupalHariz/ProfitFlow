<?php

namespace Tests\Feature\Http\Transactions;

use App\Actions\Transactions\CompleteTransaction;
use App\Enums\TransactionStatus;
use App\Jobs\DistributeTransactionProfit;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;

class TransactionLifecycleTest extends TransactionHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_guest_cannot_complete_cancel_or_retry(): void
    {
        $creator = $this->user();
        $transaction = $this->draft(
            $creator,
            Customer::factory()->create()
        );

        $this->post(route('transactions.complete', $transaction))
            ->assertRedirect(route('login'));
        $this->post(route('transactions.cancel', $transaction))
            ->assertRedirect(route('login'));
        $this->post(
            route('transactions.retry-distribution', $transaction)
        )->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_complete(): void
    {
        $creator = $this->user();
        $transaction = $this->draft(
            $creator,
            Customer::factory()->create()
        );
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($unverified)
            ->post(route('transactions.complete', $transaction))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_cannot_retry(): void
    {
        $creator = $this->user();
        $transaction = $this->draft(
            $creator,
            Customer::factory()->create()
        );
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($unverified)
            ->post(
                route('transactions.retry-distribution', $transaction)
            )
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_cannot_cancel(): void
    {
        $creator = $this->user();
        $transaction = $this->draft(
            $creator,
            Customer::factory()->create()
        );
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($unverified)
            ->post(route('transactions.cancel', $transaction))
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(
            TransactionStatus::Draft,
            $transaction->fresh()?->status
        );
    }

    public function test_verified_user_can_cancel_draft_with_server_owned_status_and_timestamp(): void
    {
        $user = $this->user();
        $transaction = $this->draft(
            $user,
            Customer::factory()->create()
        );
        $itemBefore = (array) DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->firstOrFail();

        $response = $this->actingAs($user)
            ->post(route('transactions.cancel', $transaction), [
                'status' => TransactionStatus::Distributed->value,
                'cancelled_at' => '2000-01-01 00:00:00',
                'completed_at' => now(),
                'total_revenue' => '999999.99',
            ]);

        $fresh = $transaction->fresh();
        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertInertiaFlash(
                'toast.message',
                'Transaction cancelled.'
            );
        $this->assertSame(
            TransactionStatus::Cancelled,
            $fresh?->status
        );
        $this->assertNotNull($fresh?->cancelled_at);
        $this->assertNull($fresh?->completed_at);
        $this->assertSame('251.00', $fresh?->total_revenue);
        $this->assertSame(
            $itemBefore,
            (array) DB::table('transaction_items')
                ->where('transaction_id', $transaction->id)
                ->firstOrFail()
        );
        $this->assertSame(0, $transaction->distributions()->count());
        Queue::assertNothingPushed();
    }

    #[DataProvider('nonCancellableHttpStatuses')]
    public function test_crafted_request_cannot_cancel_finalized_transaction(
        TransactionStatus $status
    ): void {
        $user = $this->user();
        $transaction = $this->createTransaction(
            creator: $user,
            attributes: ['status' => $status]
        );
        $before = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->from(route('transactions.show', $transaction))
            ->post(route('transactions.cancel', $transaction), [
                'status' => TransactionStatus::Cancelled->value,
                'cancelled_at' => now(),
            ])
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertSessionHasErrors('transaction');

        $this->assertSame(
            $before,
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        Queue::assertNothingPushed();
    }

    /**
     * @return array<string, array{TransactionStatus}>
     */
    public static function nonCancellableHttpStatuses(): array
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

    public function test_verified_user_completes_through_the_action_with_server_owned_finances_and_rule(): void
    {
        $user = $this->user();
        [$transaction, $version] = $this->readyDraft($user);

        $response = $this->actingAs($user)
            ->post(route('transactions.complete', $transaction), [
                'status' => TransactionStatus::Distributed->value,
                'completed_at' => '2000-01-01 00:00:00',
                'completed_by_user_id' => 999_999,
                'total_revenue' => '999999.99',
                'total_production_cost' => '0.00',
                'total_net_profit' => '999999.99',
                'applied_profit_scheme_version_id' => 999_999,
                'rule_resolved_at' => '2000-01-01 00:00:00',
                'failure_reason' => 'client controlled',
                'distribution_amount' => '999999.99',
            ]);

        $fresh = $transaction->fresh();
        $item = $transaction->items()->sole();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertInertiaFlash(
                'toast.message',
                'Transaction completed and queued for profit distribution.'
            );
        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $fresh?->status
        );
        $this->assertSame($user->id, $fresh?->completed_by_user_id);
        $this->assertNotNull($fresh?->completed_at);
        $this->assertSame('300.00', $fresh?->total_revenue);
        $this->assertSame(
            '200.00',
            $fresh?->total_production_cost
        );
        $this->assertSame('100.00', $fresh?->total_net_profit);
        $this->assertSame(
            $version->id,
            $item->applied_profit_scheme_version_id
        );
        $this->assertNotNull($item->rule_resolved_at);
        $this->assertSame(0, $transaction->distributions()->count());
        Queue::assertPushedOn(
            'critical',
            DistributeTransactionProfit::class,
            static fn (
                DistributeTransactionProfit $job
            ): bool => $job->transactionId === $transaction->id
                && $job->afterCommit === true
        );
    }

    public function test_expected_completion_failure_is_user_readable_and_rolls_back(): void
    {
        $user = $this->user();
        $product = Product::factory()->create([
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $transaction = $this->draft(
            $user,
            Customer::factory()->create(),
            $product
        );

        $this->actingAs($user)
            ->from(route('transactions.show', $transaction))
            ->post(route('transactions.complete', $transaction))
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertSessionHasErrors('transaction');

        $this->assertSame(
            TransactionStatus::Draft,
            $transaction->fresh()?->status
        );
        $this->assertNull($transaction->fresh()?->completed_at);
        $this->assertNull(
            $transaction->items()
                ->sole()
                ->applied_profit_scheme_version_id
        );
        $this->assertSame(0, $transaction->distributions()->count());
        Queue::assertNotPushed(DistributeTransactionProfit::class);
    }

    public function test_non_draft_transaction_cannot_be_completed_again(): void
    {
        $user = $this->user();
        [$transaction] = $this->readyDraft($user);
        app(CompleteTransaction::class)->execute($transaction, $user);
        Queue::fake();

        $this->actingAs($user)
            ->from(route('transactions.show', $transaction))
            ->post(route('transactions.complete', $transaction))
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertSessionHasErrors('transaction');

        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $transaction->fresh()?->status
        );
        Queue::assertNotPushed(DistributeTransactionProfit::class);
    }

    public function test_failed_distribution_retry_preserves_completion_and_frozen_rule_data(): void
    {
        $user = $this->user();
        [$transaction, $version] = $this->readyDraft($user);
        $completed = app(CompleteTransaction::class)->execute(
            $transaction,
            $user
        );
        $completed->update([
            'status' => TransactionStatus::DistributionFailed,
            'failure_reason' => 'Safe failure reason.',
            'distribution_started_at' => '2026-07-23 10:00:00',
        ]);
        $itemBefore = (array) DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->firstOrFail();
        $completedAt = $completed->completed_at?->toIso8601String();
        Queue::fake();

        $response = $this->actingAs($user)
            ->post(
                route('transactions.retry-distribution', $transaction)
            );

        $fresh = $transaction->fresh();
        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertInertiaFlash(
                'toast.message',
                'Profit distribution has been queued for retry.'
            );
        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $fresh?->status
        );
        $this->assertNull($fresh?->failure_reason);
        $this->assertNull($fresh?->distribution_started_at);
        $this->assertSame(
            $completedAt,
            $fresh?->completed_at?->toIso8601String()
        );
        $this->assertSame($user->id, $fresh?->completed_by_user_id);
        $this->assertSame(
            $version->id,
            $transaction->items()
                ->sole()
                ->applied_profit_scheme_version_id
        );
        $this->assertSame(
            $itemBefore,
            (array) DB::table('transaction_items')
                ->where('transaction_id', $transaction->id)
                ->firstOrFail()
        );
        $this->assertSame(0, $transaction->distributions()->count());
        Queue::assertPushedOn(
            'critical',
            DistributeTransactionProfit::class
        );
    }

    public function test_pending_distribution_can_be_safely_redriven_without_mutation(): void
    {
        $user = $this->user();
        [$transaction] = $this->readyDraft($user);
        app(CompleteTransaction::class)->execute($transaction, $user);
        $headerBefore = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();
        $itemBefore = (array) DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->firstOrFail();
        Queue::fake();

        $response = $this->actingAs($user)
            ->post(
                route('transactions.retry-distribution', $transaction)
            );

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertInertiaFlash(
                'toast.message',
                'Profit distribution has been requeued.'
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
        Queue::assertPushedOn(
            'critical',
            DistributeTransactionProfit::class
        );
    }

    #[DataProvider('rejectedRetryStatuses')]
    public function test_processing_draft_and_cancelled_retry_failures_are_user_readable(
        TransactionStatus $status
    ): void {
        $user = $this->user();
        $transaction = $this->createTransaction(
            creator: $user,
            attributes: ['status' => $status]
        );

        $this->actingAs($user)
            ->from(route('transactions.show', $transaction))
            ->post(
                route('transactions.retry-distribution', $transaction)
            )
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertSessionHasErrors('transaction');

        $this->assertSame($status, $transaction->fresh()?->status);
        Queue::assertNotPushed(DistributeTransactionProfit::class);
    }

    /**
     * @return array<string, array{TransactionStatus}>
     */
    public static function rejectedRetryStatuses(): array
    {
        return [
            'processing' => [
                TransactionStatus::ProcessingDistribution,
            ],
            'draft' => [TransactionStatus::Draft],
            'cancelled' => [TransactionStatus::Cancelled],
        ];
    }

    public function test_distributed_retry_is_an_unchanged_no_op(): void
    {
        $user = $this->user();
        $transaction = $this->createTransaction(
            creator: $user,
            attributes: [
                'status' => TransactionStatus::Distributed,
                'completed_by_user_id' => $user->id,
                'completed_at' => '2026-07-23 09:00:00',
                'distribution_started_at' => '2026-07-23 09:01:00',
                'distributed_at' => '2026-07-23 09:02:00',
            ]
        );
        $before = (array) DB::table('transactions')
            ->where('id', $transaction->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->post(
                route('transactions.retry-distribution', $transaction)
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertInertiaFlash(
                'toast.message',
                'Profit distribution is already complete.'
            );

        $this->assertSame(
            $before,
            (array) DB::table('transactions')
                ->where('id', $transaction->id)
                ->firstOrFail()
        );
        Queue::assertNotPushed(DistributeTransactionProfit::class);
    }

    public function test_inconsistent_failed_ledger_is_a_user_readable_error_and_rows_are_unchanged(): void
    {
        $user = $this->user();
        [$transaction, $version, $recipient] = $this->readyDraft(
            $user
        );
        app(CompleteTransaction::class)->execute($transaction, $user);
        $transaction->update([
            'status' => TransactionStatus::DistributionFailed,
            'failure_reason' => 'Previous failure.',
        ]);
        $item = $transaction->items()->sole();
        $row = $this->createDistribution(
            $item,
            $version,
            $recipient,
            ['amount' => '100.00']
        );
        Queue::fake();

        $this->actingAs($user)
            ->from(route('transactions.show', $transaction))
            ->post(
                route('transactions.retry-distribution', $transaction)
            )
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertSessionHasErrors('transaction');

        $this->assertSame(
            TransactionStatus::DistributionFailed,
            $transaction->fresh()?->status
        );
        $this->assertNotNull($row->fresh());
        $this->assertSame(1, $transaction->distributions()->count());
        Queue::assertNotPushed(DistributeTransactionProfit::class);
    }

    /**
     * @return array{Transaction, ProfitSchemeVersion, ProfitRecipient}
     */
    private function readyDraft(User $user): array
    {
        $category = $this->createCategory();
        $product = $this->createProduct($category, [
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $scheme = $this->createScheme($user);
        $version = $this->createSchemeVersion(
            $scheme,
            $user,
            [
                'product_category_id' => $category->id,
                'minimum_profit' => null,
                'maximum_profit' => null,
                'effective_from' => '2026-01-01',
                'effective_until' => null,
                'priority' => 0,
                'is_active' => true,
                'published_at' => '2026-07-01 00:00:00',
            ]
        );
        $recipient = $this->createCompanyRecipient();
        $this->createAllocation($version, $recipient);
        $transaction = $this->draft(
            $user,
            Customer::factory()->create(),
            $product
        );

        return [$transaction, $version, $recipient];
    }
}
