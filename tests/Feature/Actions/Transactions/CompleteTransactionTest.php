<?php

namespace Tests\Feature\Actions\Transactions;

use App\Enums\TransactionStatus;
use App\Models\ProfitDistribution;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class CompleteTransactionTest extends TransactionActionTestCase
{
    public function test_it_atomically_completes_from_stored_snapshots_and_returns_ordered_items(): void
    {
        CarbonImmutable::setTestNow('2026-07-23 14:15:16');
        Queue::fake();
        $category = $this->createCategory(['name' => 'Original Category']);
        $firstProduct = $this->createProduct($category, [
            'name' => 'Original First',
            'sku' => 'ORIGINAL-FIRST',
            'default_selling_price' => '150.25',
            'default_production_cost' => '70.10',
        ]);
        $secondProduct = $this->createProduct($category, [
            'name' => 'Original Second',
            'sku' => 'ORIGINAL-SECOND',
            'default_selling_price' => '90.00',
            'default_production_cost' => '40.00',
        ]);
        $transaction = $this->createDraft($this->validPayload(items: [
            ['product_id' => $secondProduct->id, 'quantity' => 3],
            ['product_id' => $firstProduct->id, 'quantity' => 2],
        ]));
        $itemIds = $transaction->items->pluck('id')->all();
        $snapshots = $transaction->items
            ->map(fn ($item): array => $item->only([
                'id',
                'product_id',
                'product_category_id',
                'product_name_snapshot',
                'product_sku_snapshot',
                'category_name_snapshot',
                'quantity',
                'unit_selling_price',
                'unit_production_cost',
            ]))
            ->all();
        $originalHeader = [
            'transaction_number' => $transaction->transaction_number,
            'customer_id' => $transaction->customer_id,
            'transaction_date' => $transaction->transaction_date
                ->toDateString(),
            'created_by_user_id' => $transaction->created_by_user_id,
            'created_at' => $transaction->created_at?->format(
                'Y-m-d H:i:s'
            ),
        ];
        $staleCompleter = $this->createUser();

        DB::table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->update([
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
                'failure_reason' => 'Stale draft state',
                'completed_by_user_id' => $staleCompleter->id,
                'completed_at' => '2026-01-01 00:00:00',
                'distribution_started_at' => '2026-01-01 00:01:00',
                'distributed_at' => '2026-01-01 00:02:00',
                'cancelled_at' => '2026-01-01 00:03:00',
            ]);

        $this->createPublishedRule($category);
        $firstProduct->update([
            'name' => 'Changed First',
            'sku' => 'CHANGED-FIRST',
            'default_selling_price' => '999.99',
            'default_production_cost' => '888.88',
        ]);
        $secondProduct->update([
            'name' => 'Changed Second',
            'sku' => 'CHANGED-SECOND',
            'default_selling_price' => '777.77',
            'default_production_cost' => '666.66',
        ]);
        $category->update(['name' => 'Changed Category']);
        $completer = $this->createUser();

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $completed = $this->complete($transaction, $completer);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $this->assertTrue($completed->relationLoaded('items'));
        $this->assertSame(
            $itemIds,
            $completed->items->pluck('id')->all()
        );
        $this->assertSame(
            $snapshots,
            $completed->items
                ->map(fn ($item): array => $item->only([
                    'id',
                    'product_id',
                    'product_category_id',
                    'product_name_snapshot',
                    'product_sku_snapshot',
                    'category_name_snapshot',
                    'quantity',
                    'unit_selling_price',
                    'unit_production_cost',
                ]))
                ->all()
        );
        $this->assertSame(
            ['270.00', '300.50'],
            $completed->items->pluck('total_revenue')->all()
        );
        $this->assertSame(
            ['120.00', '140.20'],
            $completed->items->pluck('total_production_cost')->all()
        );
        $this->assertSame(
            ['150.00', '160.30'],
            $completed->items->pluck('net_profit')->all()
        );
        $this->assertSame('570.50', $completed->total_revenue);
        $this->assertSame(
            '260.20',
            $completed->total_production_cost
        );
        $this->assertSame('310.30', $completed->total_net_profit);
        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $completed->status
        );
        $this->assertSame(
            $completer->id,
            $completed->completed_by_user_id
        );
        $this->assertSame(
            '2026-07-23 14:15:16',
            $completed->completed_at?->format('Y-m-d H:i:s')
        );
        $this->assertNull($completed->failure_reason);
        $this->assertNull($completed->distribution_started_at);
        $this->assertNull($completed->distributed_at);
        $this->assertNull($completed->cancelled_at);
        $this->assertSame(
            $originalHeader,
            [
                'transaction_number' => $completed->transaction_number,
                'customer_id' => $completed->customer_id,
                'transaction_date' => $completed->transaction_date
                    ->toDateString(),
                'created_by_user_id' => $completed->created_by_user_id,
                'created_at' => $completed->created_at?->format(
                    'Y-m-d H:i:s'
                ),
            ]
        );
        $this->assertSame(
            $itemIds,
            $completed->items->pluck('id')->sort()->values()->all()
        );
        $sql = strtolower(collect($queries)->pluck('query')->join(' '));
        $this->assertStringNotContainsString(
            ' from "products"',
            $sql
        );
        $this->assertStringNotContainsString(
            ' from "product_categories"',
            $sql
        );
        $this->assertSame(0, ProfitDistribution::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_a_transaction_with_only_non_positive_profit_still_becomes_pending_distribution(): void
    {
        Queue::fake();
        $category = $this->createCategory();
        $breakEven = $this->createProduct($category, [
            'default_selling_price' => '100.00',
            'default_production_cost' => '100.00',
        ]);
        $loss = $this->createProduct($category, [
            'default_selling_price' => '75.00',
            'default_production_cost' => '100.00',
        ]);
        $transaction = $this->createDraft(
            $this->validPayload(items: [
                ['product_id' => $breakEven->id, 'quantity' => 1],
                ['product_id' => $loss->id, 'quantity' => 2],
            ])
        );

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $completed = $this->complete($transaction);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $completed->status
        );
        $this->assertSame('-50.00', $completed->total_net_profit);
        $this->assertSame(
            [null, null],
            $completed->items
                ->pluck('applied_profit_scheme_version_id')
                ->all()
        );
        $this->assertSame(
            [null, null],
            $completed->items->pluck('rule_resolved_at')->all()
        );
        $this->assertFalse(
            collect($queries)->contains(
                static fn (array $query): bool => str_contains(
                    strtolower($query['query']),
                    'profit_scheme_versions'
                )
            ),
            'Non-positive items must not invoke rule resolution.'
        );
        Queue::assertNothingPushed();
    }
}
