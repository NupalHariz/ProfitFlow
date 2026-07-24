<?php

namespace Tests\Feature\Actions\Transactions;

use App\Actions\Transactions\CompleteTransaction;
use App\Actions\Transactions\CreateTransaction;
use App\Actions\Transactions\UpdateDraftTransaction;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesDomainRecords;
use Tests\TestCase;

abstract class TransactionActionTestCase extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    protected User $creator;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', config('database.default'));

        Queue::fake();
        $this->creator = $this->createUser();
        $this->customer = $this->createCustomer();
    }

    /**
     * @param  list<array<string, mixed>>|null  $items
     * @return array{
     *     customer_id: int,
     *     transaction_date: string,
     *     items: list<array<string, mixed>>
     * }
     */
    protected function validPayload(
        ?Product $product = null,
        ?array $items = null
    ): array {
        $product ??= $this->createProduct();

        return [
            'customer_id' => $this->customer->id,
            'transaction_date' => '2026-07-23',
            'items' => $items ?? [[
                'product_id' => $product->id,
                'quantity' => 2,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function createDraft(array $payload): Transaction
    {
        return app(CreateTransaction::class)->execute(
            $payload,
            $this->creator
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function updateDraft(
        Transaction $transaction,
        array $payload
    ): Transaction {
        return app(UpdateDraftTransaction::class)->execute(
            $transaction,
            $payload
        );
    }

    protected function complete(
        Transaction $transaction,
        ?User $completer = null
    ): Transaction {
        return app(CompleteTransaction::class)->execute(
            $transaction,
            $completer ?? $this->creator
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createPublishedRule(
        ProductCategory $category,
        array $attributes = []
    ): ProfitSchemeVersion {
        $scheme = $this->createScheme($this->creator);
        $version = $this->createSchemeVersion(
            $scheme,
            $this->creator,
            array_merge([
                'product_category_id' => $category->id,
                'minimum_profit' => null,
                'maximum_profit' => null,
                'effective_from' => '2026-01-01',
                'effective_until' => null,
                'priority' => 0,
                'is_active' => true,
                'published_at' => '2026-07-01 00:00:00',
            ], $attributes)
        );
        $this->createAllocation(
            $version,
            $this->createCompanyRecipient()
        );

        return $version;
    }
}
