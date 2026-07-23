<?php

namespace Tests\Feature\Actions\Transactions;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidTransactionDraft;
use App\Exceptions\Transactions\TransactionNotEditable;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class UpdateDraftTransactionTest extends TransactionActionTestCase
{
    public function test_it_fully_replaces_a_draft_and_preserves_server_owned_header_fields(): void
    {
        $originalProduct = $this->createProduct(attributes: [
            'sku' => 'ORIGINAL',
            'default_selling_price' => '20.00',
            'default_production_cost' => '10.00',
        ]);
        $transaction = $this->createDraft(
            $this->validPayload($originalProduct)
        );
        $originalNumber = $transaction->transaction_number;
        $replacementCustomer = $this->createCustomer();
        $firstReplacement = $this->createProduct(attributes: [
            'name' => 'Replacement One',
            'sku' => 'REPLACE-1',
            'default_selling_price' => '100.00',
            'default_production_cost' => '25.00',
        ]);
        $secondReplacement = $this->createProduct(attributes: [
            'name' => 'Replacement Two',
            'sku' => 'REPLACE-2',
            'default_selling_price' => '80.00',
            'default_production_cost' => '50.00',
        ]);
        $payload = [
            'customer_id' => $replacementCustomer->id,
            'transaction_date' => '2026-08-01',
            'items' => [
                [
                    'product_id' => $secondReplacement->id,
                    'quantity' => 3,
                    'unit_selling_price' => '70',
                ],
                [
                    'product_id' => $firstReplacement->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $updated = $this->updateDraft($transaction, $payload);

        $this->assertSame($replacementCustomer->id, $updated->customer_id);
        $this->assertSame(
            '2026-08-01',
            $updated->transaction_date->toDateString()
        );
        $this->assertSame($originalNumber, $updated->transaction_number);
        $this->assertSame(TransactionStatus::Draft, $updated->status);
        $this->assertSame($this->creator->id, $updated->created_by_user_id);
        $this->assertNull($updated->completed_by_user_id);
        $this->assertNull($updated->completed_at);
        $this->assertNull($updated->distribution_started_at);
        $this->assertNull($updated->distributed_at);
        $this->assertNull($updated->cancelled_at);
        $this->assertNull($updated->failure_reason);
        $this->assertSame(
            ['REPLACE-2', 'REPLACE-1'],
            $updated->items->pluck('product_sku_snapshot')->all()
        );
        $this->assertSame(
            ['Replacement Two', 'Replacement One'],
            $updated->items->pluck('product_name_snapshot')->all()
        );
        $this->assertSame(
            ['70.00', '100.00'],
            $updated->items->pluck('unit_selling_price')->all()
        );
        $this->assertSame(
            ['50.00', '25.00'],
            $updated->items->pluck('unit_production_cost')->all()
        );
        $this->assertSame(['60.00', '150.00'], $updated->items
            ->pluck('net_profit')
            ->all());
        $this->assertSame('410.00', $updated->total_revenue);
        $this->assertSame('200.00', $updated->total_production_cost);
        $this->assertSame('210.00', $updated->total_net_profit);
        $this->assertSame(
            0,
            TransactionItem::query()
                ->where('transaction_id', $transaction->id)
                ->where('product_id', $originalProduct->id)
                ->count()
        );
    }

    public function test_replacement_rebuilds_snapshots_from_current_product_master_values(): void
    {
        $category = $this->createCategory(['name' => 'Original Category']);
        $product = $this->createProduct($category, [
            'name' => 'Original Product',
            'sku' => 'ORIGINAL-SKU',
            'default_selling_price' => '100.00',
            'default_production_cost' => '60.00',
        ]);
        $transaction = $this->createDraft($this->validPayload($product));

        $category->update(['name' => 'Current Category']);
        $product->update([
            'name' => 'Current Product',
            'sku' => 'CURRENT-SKU',
            'default_selling_price' => '200.00',
            'default_production_cost' => '75.00',
        ]);

        $updated = $this->updateDraft(
            $transaction,
            $this->validPayload($product)
        );
        $item = $updated->items->sole();

        $this->assertSame('Current Product', $item->product_name_snapshot);
        $this->assertSame('CURRENT-SKU', $item->product_sku_snapshot);
        $this->assertSame('Current Category', $item->category_name_snapshot);
        $this->assertSame('200.00', $item->unit_selling_price);
        $this->assertSame('75.00', $item->unit_production_cost);
        $this->assertSame('400.00', $item->total_revenue);
        $this->assertSame('150.00', $item->total_production_cost);
        $this->assertSame('250.00', $item->net_profit);
    }

    public function test_invalid_replacement_preserves_old_header_items_and_totals(): void
    {
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '150.00',
            'default_production_cost' => '80.00',
        ]);
        $transaction = $this->createDraft($this->validPayload($product));
        $beforeHeader = $this->headerState($transaction);
        $beforeItems = $this->itemState($transaction);
        $replacementCustomer = $this->createCustomer();

        try {
            $this->updateDraft($transaction, [
                'customer_id' => $replacementCustomer->id,
                'transaction_date' => '2027-01-01',
                'items' => [[
                    'product_id' => 2_147_483_647,
                    'quantity' => 1,
                ]],
            ]);
            $this->fail('Expected invalid replacement to fail.');
        } catch (InvalidTransactionDraft) {
        }

        $fresh = $transaction->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame($beforeHeader, $this->headerState($fresh));
        $this->assertSame($beforeItems, $this->itemState($fresh));
    }

    public function test_replacement_insertion_failure_rolls_back_header_items_and_totals(): void
    {
        $original = $this->createProduct(attributes: [
            'default_selling_price' => '150.00',
            'default_production_cost' => '80.00',
        ]);
        $transaction = $this->createDraft($this->validPayload($original));
        $beforeHeader = $this->headerState($transaction);
        $beforeItems = $this->itemState($transaction);
        $newCustomer = $this->createCustomer();
        $firstReplacement = $this->createProduct();
        $secondReplacement = $this->createProduct();
        $creatingCount = 0;
        TransactionItem::creating(
            static function () use (&$creatingCount): void {
                $creatingCount++;

                if ($creatingCount === 2) {
                    throw new RuntimeException(
                        'Simulated replacement insertion failure.'
                    );
                }
            }
        );

        try {
            $this->updateDraft($transaction, [
                'customer_id' => $newCustomer->id,
                'transaction_date' => '2027-01-01',
                'items' => [
                    [
                        'product_id' => $firstReplacement->id,
                        'quantity' => 1,
                    ],
                    [
                        'product_id' => $secondReplacement->id,
                        'quantity' => 1,
                    ],
                ],
            ]);
            $this->fail('Expected replacement insertion to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated replacement insertion failure.',
                $exception->getMessage()
            );
        } finally {
            TransactionItem::flushEventListeners();
        }

        $fresh = $transaction->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame($beforeHeader, $this->headerState($fresh));
        $this->assertSame($beforeItems, $this->itemState($fresh));
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_non_draft_transactions_cannot_be_updated(
        TransactionStatus $status
    ): void {
        $product = $this->createProduct();
        $transaction = $this->createDraft($this->validPayload($product));
        $transaction->update(['status' => $status]);
        $beforeHeader = $this->headerState($transaction);
        $beforeItems = $this->itemState($transaction);

        try {
            $this->updateDraft(
                $transaction,
                $this->validPayload($product)
            );
            $this->fail('Expected non-draft transaction to be immutable.');
        } catch (TransactionNotEditable $exception) {
            $this->assertStringContainsString(
                $status->value,
                $exception->getMessage()
            );
        }

        $fresh = $transaction->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame($beforeHeader, $this->headerState($fresh));
        $this->assertSame($beforeItems, $this->itemState($fresh));
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

    public function test_draft_update_locks_the_transaction_row_for_update(): void
    {
        $product = $this->createProduct();
        $transaction = $this->createDraft($this->validPayload($product));
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->updateDraft(
                $transaction,
                $this->validPayload($product)
            );
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $usedRowLock = collect($queries)->contains(
            static fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'for update'
            )
        );

        $this->assertTrue(
            $usedRowLock,
            'Expected the draft transaction query to use FOR UPDATE.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function headerState(Transaction $transaction): array
    {
        $fresh = Transaction::query()->findOrFail($transaction->id);

        return Arr::only($fresh->getRawOriginal(), [
            'transaction_number',
            'customer_id',
            'transaction_date',
            'status',
            'total_revenue',
            'total_production_cost',
            'total_net_profit',
            'failure_reason',
            'created_by_user_id',
            'completed_by_user_id',
            'completed_at',
            'distribution_started_at',
            'distributed_at',
            'cancelled_at',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemState(Transaction $transaction): array
    {
        return TransactionItem::query()
            ->where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (TransactionItem $item): array => $item->only([
                'id',
                'transaction_id',
                'product_id',
                'product_category_id',
                'applied_profit_scheme_version_id',
                'product_name_snapshot',
                'product_sku_snapshot',
                'category_name_snapshot',
                'quantity',
                'unit_selling_price',
                'unit_production_cost',
                'total_revenue',
                'total_production_cost',
                'net_profit',
                'rule_resolved_at',
            ]))
            ->all();
    }
}
