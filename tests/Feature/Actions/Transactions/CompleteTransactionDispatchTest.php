<?php

namespace Tests\Feature\Actions\Transactions;

use App\Actions\Transactions\CompleteTransaction;
use App\Actions\Transactions\CreateTransaction;
use App\Jobs\DistributeTransactionProfit;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesDomainRecords;
use Tests\TestCase;
use Throwable;

class CompleteTransactionDispatchTest extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    public function test_completion_enqueues_the_id_only_job_after_the_outermost_commit(): void
    {
        config(['queue.default' => 'database']);
        $transaction = $this->draftReadyForCompletion();
        DB::beginTransaction();

        try {
            $completed = app(CompleteTransaction::class)->execute(
                $transaction,
                $this->createUser()
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
        $this->assertSame($completed->id, $command->transactionId);
        $this->assertSame('critical', $queued->queue);
        $this->assertTrue($command->afterCommit);
        $this->assertStringNotContainsString(
            'App\\Models\\Transaction',
            $payload['data']['command']
        );
    }

    public function test_an_outer_completion_rollback_never_enqueues_the_job(): void
    {
        config(['queue.default' => 'database']);
        $transaction = $this->draftReadyForCompletion();
        DB::beginTransaction();

        app(CompleteTransaction::class)->execute(
            $transaction,
            $this->createUser()
        );

        $this->assertSame(0, DB::table('jobs')->count());
        DB::rollBack();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame('draft', $transaction->fresh()?->status->value);
    }

    private function draftReadyForCompletion(): Transaction
    {
        $creator = $this->createUser();
        $category = $this->createCategory();
        $product = $this->createProduct($category, [
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $version = $this->createSchemeVersion(
            $this->createScheme($creator),
            $creator,
            [
                'product_category_id' => $category->id,
                'minimum_profit' => null,
                'maximum_profit' => null,
                'effective_from' => '2026-01-01',
                'published_at' => '2026-07-01 00:00:00',
            ]
        );
        $this->createAllocation(
            $version,
            $this->createCompanyRecipient()
        );

        return app(CreateTransaction::class)->execute([
            'customer_id' => $this->createCustomer()->id,
            'transaction_date' => '2026-07-23',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
            ]],
        ], $creator);
    }
}
