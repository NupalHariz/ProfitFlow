<?php

namespace Tests\Feature\Actions\Transactions;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\ProfitRuleNotFoundForTransactionItem;
use App\Jobs\DistributeTransactionProfit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class CompleteTransactionRuleResolutionTest extends TransactionActionTestCase
{
    public function test_positive_profit_freezes_the_rule_resolved_from_transaction_date_and_category(): void
    {
        CarbonImmutable::setTestNow('2026-12-31 23:59:58');
        $category = $this->createCategory();
        $product = $this->createProduct($category, [
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $transaction = $this->createDraft([
            'customer_id' => $this->customer->id,
            'transaction_date' => '2026-07-15',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
            ]],
        ]);
        $expected = $this->createPublishedRule($category, [
            'effective_from' => '2026-07-01',
            'effective_until' => '2026-07-31',
            'minimum_profit' => '100.00',
            'maximum_profit' => '100.00',
            'priority' => 10,
        ]);
        $this->createPublishedRule($category, [
            'effective_from' => '2026-08-01',
            'priority' => 100,
        ]);
        $this->createPublishedRule(
            $this->createCategory(),
            ['priority' => 200]
        );

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $completed = $this->complete($transaction);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $item = $completed->items->sole();
        $this->assertSame(
            $expected->id,
            $item->applied_profit_scheme_version_id
        );
        $this->assertSame(
            '2026-12-31 23:59:58',
            $item->rule_resolved_at?->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            $completed->completed_at?->format('Y-m-d H:i:s'),
            $item->rule_resolved_at?->format('Y-m-d H:i:s')
        );
        $this->assertFalse($expected->relationLoaded('allocations'));
        $this->assertFalse(
            collect($queries)->contains(
                static fn (array $query): bool => str_contains(
                    strtolower($query['query']),
                    'profit_scheme_allocations'
                )
            ),
            'Completion must not load allocation rows.'
        );
    }

    public function test_category_null_rule_cannot_be_frozen_for_a_categorized_item(): void
    {
        $category = $this->createCategory();
        $product = $this->createProduct($category, [
            'default_selling_price' => '10.00',
            'default_production_cost' => '1.00',
        ]);
        $scheme = $this->createScheme($this->creator);
        $categoryNullRule = $this->createSchemeVersion(
            $scheme,
            $this->creator,
            [
                'product_category_id' => null,
                'effective_from' => '2026-01-01',
                'is_active' => true,
                'published_at' => '2026-01-01 00:00:00',
            ]
        );
        $this->createAllocation(
            $categoryNullRule,
            $this->createCompanyRecipient()
        );
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );

        try {
            $this->complete($transaction);
            $this->fail('Expected category-null rule to be ignored.');
        } catch (ProfitRuleNotFoundForTransactionItem) {
            // Expected.
        }

        $fresh = $transaction->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(TransactionStatus::Draft, $fresh->status);
        $this->assertNull(
            $fresh->items()->sole()->applied_profit_scheme_version_id
        );
        $this->assertDatabaseCount('profit_distributions', 0);
        Queue::assertNotPushed(DistributeTransactionProfit::class);
    }

    public function test_completion_freezes_current_version_before_comparing_cross_scheme_priority(): void
    {
        $category = $this->createCategory();
        $product = $this->createProduct($category, [
            'default_selling_price' => '200.00',
            'default_production_cost' => '100.00',
        ]);
        $scheme = $this->createScheme($this->creator);
        $older = $this->createSchemeVersion(
            $scheme,
            $this->creator,
            [
                'version' => 1,
                'product_category_id' => $category->id,
                'effective_from' => '2026-01-01',
                'priority' => 100,
                'is_active' => true,
                'published_at' => '2026-01-01 00:00:00',
            ]
        );
        $current = $this->createSchemeVersion(
            $scheme,
            $this->creator,
            [
                'version' => 2,
                'product_category_id' => $category->id,
                'effective_from' => '2026-01-01',
                'priority' => 0,
                'is_active' => true,
                'published_at' => '2026-02-01 00:00:00',
            ]
        );
        $this->createAllocation(
            $older,
            $this->createCompanyRecipient()
        );
        $this->createAllocation(
            $current,
            $this->createCompanyRecipient()
        );
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );

        $completed = $this->complete($transaction);

        $this->assertSame(
            $current->id,
            $completed->items
                ->sole()
                ->applied_profit_scheme_version_id
        );
    }

    public function test_different_items_freeze_independently_resolved_versions(): void
    {
        $firstCategory = $this->createCategory();
        $secondCategory = $this->createCategory();
        $firstProduct = $this->createProduct($firstCategory, [
            'default_selling_price' => '200.00',
            'default_production_cost' => '100.00',
        ]);
        $secondProduct = $this->createProduct($secondCategory, [
            'default_selling_price' => '75.00',
            'default_production_cost' => '25.00',
        ]);
        $firstRule = $this->createPublishedRule($firstCategory, [
            'priority' => 10,
        ]);
        $secondRule = $this->createPublishedRule($secondCategory, [
            'priority' => 20,
        ]);
        $transaction = $this->createDraft(
            $this->validPayload(items: [
                ['product_id' => $secondProduct->id, 'quantity' => 2],
                ['product_id' => $firstProduct->id, 'quantity' => 1],
            ])
        );

        $completed = $this->complete($transaction);

        $this->assertSame(
            [$secondRule->id, $firstRule->id],
            $completed->items
                ->pluck('applied_profit_scheme_version_id')
                ->all()
        );
    }

    public function test_positive_break_even_and_loss_items_complete_with_rules_only_on_positive_profit(): void
    {
        $category = $this->createCategory();
        $positive = $this->createProduct($category, [
            'default_selling_price' => '150.00',
            'default_production_cost' => '100.00',
        ]);
        $breakEven = $this->createProduct($category, [
            'default_selling_price' => '100.00',
            'default_production_cost' => '100.00',
        ]);
        $loss = $this->createProduct($category, [
            'default_selling_price' => '80.00',
            'default_production_cost' => '100.00',
        ]);
        $rule = $this->createPublishedRule($category);
        $transaction = $this->createDraft(
            $this->validPayload(items: [
                ['product_id' => $positive->id, 'quantity' => 1],
                ['product_id' => $breakEven->id, 'quantity' => 1],
                ['product_id' => $loss->id, 'quantity' => 1],
            ])
        );

        $completed = $this->complete($transaction);

        $this->assertSame(
            ['50.00', '0.00', '-20.00'],
            $completed->items->pluck('net_profit')->all()
        );
        $this->assertSame(
            [$rule->id, null, null],
            $completed->items
                ->pluck('applied_profit_scheme_version_id')
                ->all()
        );
        $this->assertNotNull(
            $completed->items->get(0)?->rule_resolved_at
        );
        $this->assertNull(
            $completed->items->get(1)?->rule_resolved_at
        );
        $this->assertNull(
            $completed->items->get(2)?->rule_resolved_at
        );
    }

    public function test_a_newer_rule_or_later_condition_change_does_not_replace_the_frozen_version(): void
    {
        $category = $this->createCategory();
        $product = $this->createProduct($category, [
            'default_selling_price' => '200.00',
            'default_production_cost' => '100.00',
        ]);
        $frozenRule = $this->createPublishedRule($category, [
            'priority' => 10,
        ]);
        $transaction = $this->createDraft(
            $this->validPayload($product)
        );
        $completed = $this->complete($transaction);
        $itemId = $completed->items->sole()->id;

        $scheme = $frozenRule->scheme()->firstOrFail();
        $newerRule = $this->createSchemeVersion(
            $scheme,
            $this->creator,
            [
                'version' => 2,
                'product_category_id' => $category->id,
                'minimum_profit' => null,
                'maximum_profit' => null,
                'effective_from' => '2026-01-01',
                'effective_until' => null,
                'is_active' => true,
                'priority' => 100,
                'published_at' => '2026-07-24 00:00:00',
            ]
        );
        $this->createAllocation(
            $newerRule,
            $this->createCompanyRecipient()
        );
        $scheme->update(['is_active' => false]);

        $freshItem = $completed->items()
            ->whereKey($itemId)
            ->firstOrFail();

        $this->assertSame(
            $frozenRule->id,
            $freshItem->applied_profit_scheme_version_id
        );
        $this->assertNotSame(
            $newerRule->id,
            $freshItem->applied_profit_scheme_version_id
        );
    }
}
