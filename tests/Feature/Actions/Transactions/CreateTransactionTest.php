<?php

namespace Tests\Feature\Actions\Transactions;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidTransactionDraft;
use App\Models\Transaction;
use App\Models\TransactionItem;

class CreateTransactionTest extends TransactionActionTestCase
{
    public function test_it_creates_a_draft_with_server_owned_identity_and_lifecycle(): void
    {
        $product = $this->createProduct();

        $transaction = $this->createDraft($this->validPayload($product));

        $this->assertTrue($transaction->relationLoaded('items'));
        $this->assertSame(TransactionStatus::Draft, $transaction->status);
        $this->assertSame($this->creator->id, $transaction->created_by_user_id);
        $this->assertSame($this->customer->id, $transaction->customer_id);
        $this->assertMatchesRegularExpression(
            '/^TRX-[0-9A-HJKMNP-TV-Z]{26}$/',
            $transaction->transaction_number
        );
        $this->assertNull($transaction->idempotency_key);
        $this->assertNull($transaction->completed_by_user_id);
        $this->assertNull($transaction->completed_at);
        $this->assertNull($transaction->distribution_started_at);
        $this->assertNull($transaction->distributed_at);
        $this->assertNull($transaction->cancelled_at);
        $this->assertNull($transaction->failure_reason);

        $second = $this->createDraft($this->validPayload($product));

        $this->assertNotSame(
            $transaction->transaction_number,
            $second->transaction_number
        );
    }

    public function test_transaction_number_is_rejected_when_supplied_by_a_client(): void
    {
        $payload = $this->validPayload();
        $payload['transaction_number'] = 'TRX-CLIENT-CONTROLLED';

        $this->expectException(InvalidTransactionDraft::class);
        $this->expectExceptionMessage('transaction_number');

        $this->createDraft($payload);
    }

    public function test_it_uses_default_prices_and_stores_identity_and_financial_snapshots(): void
    {
        $category = $this->createCategory([
            'name' => 'Premium Drinks',
        ]);
        $product = $this->createProduct($category, [
            'name' => 'Cold Brew',
            'sku' => 'CB-001',
            'default_selling_price' => '150.50',
            'default_production_cost' => '80.25',
        ]);

        $transaction = $this->createDraft($this->validPayload($product));
        $item = $transaction->items->sole();

        $this->assertSame($product->id, $item->product_id);
        $this->assertSame($category->id, $item->product_category_id);
        $this->assertSame('Cold Brew', $item->product_name_snapshot);
        $this->assertSame('CB-001', $item->product_sku_snapshot);
        $this->assertSame('Premium Drinks', $item->category_name_snapshot);
        $this->assertSame('150.50', $item->unit_selling_price);
        $this->assertSame('80.25', $item->unit_production_cost);
        $this->assertSame('301.00', $item->total_revenue);
        $this->assertSame('160.50', $item->total_production_cost);
        $this->assertSame('140.50', $item->net_profit);
        $this->assertSame('301.00', $transaction->total_revenue);
        $this->assertSame('160.50', $transaction->total_production_cost);
        $this->assertSame('140.50', $transaction->total_net_profit);
        $this->assertNull($item->applied_profit_scheme_version_id);
        $this->assertNull($item->rule_resolved_at);
    }

    public function test_it_normalizes_a_selling_price_override_and_keeps_production_cost_trusted(): void
    {
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '150.00',
            'default_production_cost' => '80.25',
        ]);
        $payload = $this->validPayload($product);
        $payload['items'][0]['unit_selling_price'] = '125000.5';

        $transaction = $this->createDraft($payload);
        $item = $transaction->items->sole();

        $this->assertSame('125000.50', $item->unit_selling_price);
        $this->assertSame('80.25', $item->unit_production_cost);
        $this->assertSame('250001.00', $item->total_revenue);
        $this->assertSame('160.50', $item->total_production_cost);
        $this->assertSame('249840.50', $item->net_profit);
    }

    public function test_it_preserves_order_and_aggregates_profitable_break_even_and_loss_items(): void
    {
        $profitable = $this->createProduct(attributes: [
            'sku' => 'PROFIT',
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $breakEven = $this->createProduct(attributes: [
            'sku' => 'EVEN',
            'default_selling_price' => '100.00',
            'default_production_cost' => '100.00',
        ]);
        $loss = $this->createProduct(attributes: [
            'sku' => 'LOSS',
            'default_selling_price' => '75.00',
            'default_production_cost' => '100.00',
        ]);
        $items = [
            ['product_id' => $loss->id, 'quantity' => 3],
            ['product_id' => $profitable->id, 'quantity' => 2],
            ['product_id' => $breakEven->id, 'quantity' => 1],
        ];

        $transaction = $this->createDraft(
            $this->validPayload(items: $items)
        );

        $this->assertSame(
            ['LOSS', 'PROFIT', 'EVEN'],
            $transaction->items
                ->pluck('product_sku_snapshot')
                ->all()
        );
        $this->assertSame(
            ['-75.00', '100.00', '0.00'],
            $transaction->items->pluck('net_profit')->all()
        );
        $this->assertSame('625.00', $transaction->total_revenue);
        $this->assertSame('600.00', $transaction->total_production_cost);
        $this->assertSame('25.00', $transaction->total_net_profit);
    }

    public function test_duplicate_product_lines_are_allowed_and_keep_input_order(): void
    {
        $product = $this->createProduct();
        $items = [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_selling_price' => '10',
            ],
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_selling_price' => '20',
            ],
        ];

        $transaction = $this->createDraft(
            $this->validPayload(items: $items)
        );

        $this->assertCount(2, $transaction->items);
        $this->assertSame(
            ['10.00', '20.00'],
            $transaction->items->pluck('unit_selling_price')->all()
        );
    }

    public function test_product_master_changes_do_not_mutate_persisted_snapshots(): void
    {
        $category = $this->createCategory(['name' => 'Original Category']);
        $product = $this->createProduct($category, [
            'name' => 'Original Product',
            'sku' => 'ORIGINAL-SKU',
            'default_selling_price' => '150.00',
            'default_production_cost' => '90.00',
        ]);
        $transaction = $this->createDraft($this->validPayload($product));

        $product->update([
            'name' => 'Changed Product',
            'sku' => 'CHANGED-SKU',
            'default_selling_price' => '999.00',
            'default_production_cost' => '888.00',
            'is_active' => false,
        ]);
        $category->update([
            'name' => 'Changed Category',
            'is_active' => false,
        ]);
        $item = $transaction->items()->firstOrFail();

        $this->assertSame('Original Product', $item->product_name_snapshot);
        $this->assertSame('ORIGINAL-SKU', $item->product_sku_snapshot);
        $this->assertSame('Original Category', $item->category_name_snapshot);
        $this->assertSame('150.00', $item->unit_selling_price);
        $this->assertSame('90.00', $item->unit_production_cost);
    }

    public function test_item_insertion_failure_rolls_back_transaction_and_all_items(): void
    {
        $first = $this->createProduct();
        $second = $this->createProduct();
        $creatingCount = 0;
        TransactionItem::creating(
            static function () use (&$creatingCount): void {
                $creatingCount++;

                if ($creatingCount === 2) {
                    throw new \RuntimeException(
                        'Simulated second item insertion failure.'
                    );
                }
            }
        );

        try {
            $this->createDraft($this->validPayload(items: [
                ['product_id' => $first->id, 'quantity' => 1],
                ['product_id' => $second->id, 'quantity' => 1],
            ]));
            $this->fail('Expected item insertion to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Simulated second item insertion failure.',
                $exception->getMessage()
            );
        } finally {
            TransactionItem::flushEventListeners();
        }

        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, TransactionItem::query()->count());
    }
}
