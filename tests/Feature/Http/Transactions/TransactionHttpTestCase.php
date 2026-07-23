<?php

namespace Tests\Feature\Http\Transactions;

use App\Actions\Transactions\CreateTransaction;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainRecords;
use Tests\TestCase;

abstract class TransactionHttpTestCase extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', config('database.default'));
    }

    protected function user(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>|null  $items
     * @return array{
     *     customer_id: int,
     *     transaction_date: string,
     *     items: list<array<string, mixed>>
     * }
     */
    protected function payload(
        Customer $customer,
        ?Product $product = null,
        ?array $items = null
    ): array {
        $product ??= $this->createProduct();

        return [
            'customer_id' => $customer->id,
            'transaction_date' => '2026-07-23',
            'items' => $items ?? [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_selling_price' => null,
            ]],
        ];
    }

    protected function draft(
        User $creator,
        Customer $customer,
        ?Product $product = null
    ): Transaction {
        return app(CreateTransaction::class)->execute(
            $this->payload($customer, $product),
            $creator
        );
    }
}
