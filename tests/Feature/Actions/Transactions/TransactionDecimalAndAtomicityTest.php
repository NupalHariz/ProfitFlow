<?php

namespace Tests\Feature\Actions\Transactions;

use App\Exceptions\Transactions\InvalidTransactionDraft;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class TransactionDecimalAndAtomicityTest extends TransactionActionTestCase
{
    #[DataProvider('normalizableSellingPrices')]
    public function test_valid_selling_price_strings_are_normalized(
        string $input,
        string $expected
    ): void {
        $product = $this->createProduct();
        $payload = $this->validPayload($product);
        $payload['items'][0]['unit_selling_price'] = $input;

        $transaction = $this->createDraft($payload);

        $this->assertSame(
            $expected,
            $transaction->items->sole()->unit_selling_price
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function normalizableSellingPrices(): array
    {
        return [
            'zero integer form' => ['0', '0.00'],
            'zero fixed form' => ['0.00', '0.00'],
            'whole money' => ['125000', '125000.00'],
            'one decimal place' => ['125000.5', '125000.50'],
            'two decimal places' => ['125000.50', '125000.50'],
        ];
    }

    public function test_action_and_persisted_money_values_are_exact_strings(): void
    {
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '123.45',
            'default_production_cost' => '67.89',
        ]);

        $transaction = $this->createDraft($this->validPayload($product));
        $item = $transaction->items->sole();

        foreach ([
            $transaction->total_revenue,
            $transaction->total_production_cost,
            $transaction->total_net_profit,
            $item->unit_selling_price,
            $item->unit_production_cost,
            $item->total_revenue,
            $item->total_production_cost,
            $item->net_profit,
        ] as $value) {
            $this->assertIsString($value);
            $this->assertMatchesRegularExpression(
                '/^-?\d+\.\d{2}$/',
                $value
            );
        }

        $rawTransaction = DB::table('transactions')
            ->where('id', $transaction->id)
            ->first();
        $rawItem = DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->first();

        $this->assertNotNull($rawTransaction);
        $this->assertNotNull($rawItem);
        $this->assertIsString($rawTransaction->total_revenue);
        $this->assertIsString($rawTransaction->total_production_cost);
        $this->assertIsString($rawTransaction->total_net_profit);
        $this->assertIsString($rawItem->unit_selling_price);
        $this->assertIsString($rawItem->unit_production_cost);
        $this->assertIsString($rawItem->total_revenue);
        $this->assertIsString($rawItem->total_production_cost);
        $this->assertIsString($rawItem->net_profit);
    }

    public function test_large_valid_money_works_when_multiplication_stays_in_range(): void
    {
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '4999999999999999.99',
            'default_production_cost' => '0.01',
        ]);

        $transaction = $this->createDraft($this->validPayload($product));
        $item = $transaction->items->sole();

        $this->assertSame(
            '9999999999999999.98',
            $item->total_revenue
        );
        $this->assertSame('0.02', $item->total_production_cost);
        $this->assertSame(
            '9999999999999999.96',
            $item->net_profit
        );
        $this->assertSame(
            '9999999999999999.98',
            $transaction->total_revenue
        );
    }

    public function test_item_multiplication_overflow_is_rejected_atomically(): void
    {
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '9999999999999999.99',
            'default_production_cost' => '0.00',
        ]);

        $this->assertCalculationFailureLeavesNoRows(
            $this->validPayload($product),
            'calculation failed'
        );
    }

    public function test_transaction_aggregation_overflow_is_rejected_atomically(): void
    {
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '6000000000000000.00',
            'default_production_cost' => '0.00',
        ]);

        $this->assertCalculationFailureLeavesNoRows(
            $this->validPayload(items: [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $product->id, 'quantity' => 1],
            ]),
            'total calculation failed'
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertCalculationFailureLeavesNoRows(
        array $payload,
        string $messageFragment
    ): void {
        try {
            $this->createDraft($payload);
            $this->fail('Expected transaction calculation to fail.');
        } catch (InvalidTransactionDraft $exception) {
            $this->assertStringContainsString(
                $messageFragment,
                $exception->getMessage()
            );
        }

        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, TransactionItem::query()->count());
    }
}
