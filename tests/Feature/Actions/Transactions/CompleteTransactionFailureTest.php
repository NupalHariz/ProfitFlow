<?php

namespace Tests\Feature\Actions\Transactions;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidTransactionCompletion;
use App\Exceptions\Transactions\MissingTransactionItemCategory;
use App\Exceptions\Transactions\ProfitRuleNotFoundForTransactionItem;
use App\Jobs\DistributeTransactionProfit;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class CompleteTransactionFailureTest extends TransactionActionTestCase
{
    public function test_a_missing_rule_rolls_back_every_item_and_the_transaction(): void
    {
        $matchedCategory = $this->createCategory();
        $unmatchedCategory = $this->createCategory();
        $matchedProduct = $this->createProduct($matchedCategory, [
            'default_selling_price' => '200.00',
            'default_production_cost' => '100.00',
        ]);
        $unmatchedProduct = $this->createProduct($unmatchedCategory, [
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $staleRule = $this->createPublishedRule(
            $this->createCategory()
        );
        $this->createPublishedRule($matchedCategory);
        $transaction = $this->createDraft(
            $this->validPayload(items: [
                ['product_id' => $matchedProduct->id, 'quantity' => 1],
                ['product_id' => $unmatchedProduct->id, 'quantity' => 1],
            ])
        );
        DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->update([
                'applied_profit_scheme_version_id' => $staleRule->id,
                'rule_resolved_at' => '2026-01-01 00:00:00',
                'total_revenue' => '1.00',
                'total_production_cost' => '1.00',
                'net_profit' => '0.00',
            ]);
        DB::table('transactions')
            ->where('id', $transaction->id)
            ->update([
                'total_revenue' => '2.00',
                'total_production_cost' => '2.00',
                'total_net_profit' => '0.00',
            ]);
        $before = $this->storedState($transaction);
        $missingItemId = $transaction->items
            ->sortBy('id')
            ->values()
            ->get(1)
            ?->id;

        try {
            $this->complete($transaction);
            $this->fail('Expected the missing rule to reject completion.');
        } catch (ProfitRuleNotFoundForTransactionItem $exception) {
            $this->assertStringContainsString(
                "item [{$missingItemId}]",
                $exception->getMessage()
            );
        }

        $this->assertSame($before, $this->storedState($transaction));
        $this->assertSame(
            TransactionStatus::Draft,
            $transaction->fresh()?->status
        );
        $this->assertNull($transaction->fresh()?->completed_at);
        Queue::assertNotPushed(DistributeTransactionProfit::class);
    }

    public function test_a_profitable_item_without_category_identity_is_rejected(): void
    {
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );
        $itemId = $transaction->items->sole()->id;
        DB::table('transaction_items')
            ->where('id', $itemId)
            ->update(['product_category_id' => null]);
        $before = $this->storedState($transaction);

        try {
            $this->complete($transaction);
            $this->fail(
                'Expected missing category identity to reject completion.'
            );
        } catch (MissingTransactionItemCategory $exception) {
            $this->assertStringContainsString(
                "item [{$itemId}]",
                $exception->getMessage()
            );
            $this->assertStringContainsString(
                'category identity',
                $exception->getMessage()
            );
        }

        $this->assertSame($before, $this->storedState($transaction));
        Queue::assertNotPushed(DistributeTransactionProfit::class);
    }

    public function test_a_non_profitable_item_without_category_identity_can_complete(): void
    {
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '50.00',
            'default_production_cost' => '100.00',
        ]);
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );
        $itemId = $transaction->items->sole()->id;
        DB::table('transaction_items')
            ->where('id', $itemId)
            ->update(['product_category_id' => null]);

        $completed = $this->complete($transaction);
        $item = $completed->items->sole();

        $this->assertNull($item->product_category_id);
        $this->assertNull($item->applied_profit_scheme_version_id);
        $this->assertNull($item->rule_resolved_at);
    }

    #[DataProvider('invalidStoredFinancialSnapshots')]
    public function test_invalid_stored_financial_snapshots_are_rejected(
        string $field,
        mixed $value
    ): void {
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );
        $itemId = $transaction->items->sole()->id;
        $before = $this->storedState($transaction);
        TransactionItem::retrieved(
            static function (TransactionItem $item) use (
                $field,
                $itemId,
                $value
            ): void {
                if ($item->id !== $itemId) {
                    return;
                }

                $attributes = $item->getAttributes();
                $attributes[$field] = $value;
                $item->setRawAttributes($attributes, true);
            }
        );

        try {
            $this->complete($transaction);
            $this->fail(
                'Expected the invalid stored snapshot to reject completion.'
            );
        } catch (InvalidTransactionCompletion $exception) {
            $this->assertStringContainsString(
                "item [{$itemId}]",
                $exception->getMessage()
            );
            $this->assertStringContainsString(
                'financial snapshot',
                $exception->getMessage()
            );
        } finally {
            TransactionItem::flushEventListeners();
        }

        $this->assertSame($before, $this->storedState($transaction));
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidStoredFinancialSnapshots(): array
    {
        return [
            'zero quantity' => ['quantity', 0],
            'negative quantity' => ['quantity', -1],
            'malformed selling price' => [
                'unit_selling_price',
                'not-money',
            ],
            'negative selling price' => [
                'unit_selling_price',
                '-0.01',
            ],
            'over-precision selling price' => [
                'unit_selling_price',
                '10.001',
            ],
            'out-of-range selling price' => [
                'unit_selling_price',
                '10000000000000000.00',
            ],
            'malformed production cost' => [
                'unit_production_cost',
                'not-money',
            ],
            'negative production cost' => [
                'unit_production_cost',
                '-0.01',
            ],
            'over-precision production cost' => [
                'unit_production_cost',
                '10.001',
            ],
            'out-of-range production cost' => [
                'unit_production_cost',
                '10000000000000000.00',
            ],
        ];
    }

    public function test_item_calculation_overflow_rejects_completion_without_changes(): void
    {
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '100.00',
            'default_production_cost' => '0.00',
        ]);
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );
        DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->update([
                'quantity' => 2,
                'unit_selling_price' => '9999999999999999.99',
            ]);
        $before = $this->storedState($transaction);

        try {
            $this->complete($transaction);
            $this->fail('Expected financial overflow.');
        } catch (InvalidTransactionCompletion $exception) {
            $this->assertStringContainsString(
                'exceeds decimal(18, 2)',
                $exception->getMessage()
            );
        }

        $this->assertSame($before, $this->storedState($transaction));
    }

    public function test_transaction_aggregation_overflow_rejects_completion_without_changes(): void
    {
        $first = $this->createProduct(attributes: [
            'default_selling_price' => '6000000000000000.00',
            'default_production_cost' => '0.00',
        ]);
        $second = $this->createProduct(attributes: [
            'default_selling_price' => '1.00',
            'default_production_cost' => '0.00',
        ]);
        $transaction = $this->createDraft(
            $this->validPayload(items: [
                ['product_id' => $first->id, 'quantity' => 1],
                ['product_id' => $second->id, 'quantity' => 1],
            ])
        );
        $this->createPublishedRule();
        DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->where('product_id', $second->id)
            ->update(['unit_selling_price' => '5000000000000000.00']);
        $before = $this->storedState($transaction);
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->complete($transaction);
            $this->fail('Expected total aggregation overflow.');
        } catch (InvalidTransactionCompletion $exception) {
            $this->assertStringContainsString(
                'total calculation failed',
                $exception->getMessage()
            );
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame($before, $this->storedState($transaction));
        $this->assertFalse(
            collect($queries)->contains(
                static fn (array $query): bool => str_contains(
                    strtolower($query['query']),
                    'profit_scheme_versions'
                )
            ),
            'All item totals must aggregate before rule resolution begins.'
        );
    }

    public function test_a_transaction_without_items_is_rejected(): void
    {
        $transaction = $this->createTransaction(
            $this->customer,
            $this->creator
        );

        $this->expectException(InvalidTransactionCompletion::class);
        $this->expectExceptionMessage('without items');

        $this->complete($transaction);
    }

    public function test_item_persistence_failure_rolls_back_all_item_and_transaction_updates(): void
    {
        $category = $this->createCategory();
        $first = $this->createProduct($category, [
            'default_selling_price' => '200.00',
            'default_production_cost' => '100.00',
        ]);
        $second = $this->createProduct($category, [
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $transaction = $this->createDraft(
            $this->validPayload(items: [
                ['product_id' => $first->id, 'quantity' => 1],
                ['product_id' => $second->id, 'quantity' => 1],
            ])
        );
        $this->createPublishedRule($category);
        DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->update([
                'total_revenue' => '1.00',
                'total_production_cost' => '1.00',
                'net_profit' => '0.00',
            ]);
        $before = $this->storedState($transaction);
        $updates = 0;
        TransactionItem::updating(
            static function () use (&$updates): void {
                $updates++;

                if ($updates === 2) {
                    throw new RuntimeException(
                        'Simulated completion persistence failure.'
                    );
                }
            }
        );

        try {
            $this->complete($transaction);
            $this->fail('Expected item persistence to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated completion persistence failure.',
                $exception->getMessage()
            );
        } finally {
            TransactionItem::flushEventListeners();
        }

        $this->assertSame($before, $this->storedState($transaction));
    }

    /**
     * @return array{
     *     transaction: array<string, mixed>,
     *     items: list<array<string, mixed>>
     * }
     */
    private function storedState(Transaction $transaction): array
    {
        $storedTransaction = DB::table('transactions')
            ->where('id', $transaction->id)
            ->first();

        $this->assertNotNull($storedTransaction);

        return [
            'transaction' => (array) $storedTransaction,
            'items' => DB::table('transaction_items')
                ->where('transaction_id', $transaction->id)
                ->orderBy('id')
                ->get()
                ->map(
                    static fn (object $item): array => (array) $item
                )
                ->all(),
        ];
    }
}
