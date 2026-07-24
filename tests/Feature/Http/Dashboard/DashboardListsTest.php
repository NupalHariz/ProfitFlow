<?php

namespace Tests\Feature\Http\Dashboard;

use App\Enums\TransactionStatus;
use App\Queries\Dashboard\DashboardOverviewQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class DashboardListsTest extends DashboardTestCase
{
    public function test_recent_transactions_are_bounded_deterministic_and_summary_only(): void
    {
        $sameDateFirst = null;
        $sameDateSecond = null;

        foreach (range(1, 10) as $index) {
            $transaction = $this->createTransaction(attributes: [
                'transaction_number' => "TRX-RECENT-{$index}",
                'transaction_date' => $index <= 2
                    ? '2026-07-30'
                    : sprintf('2026-07-%02d', 30 - $index),
                'status' => TransactionStatus::Distributed,
                'total_revenue' => "{$index}.00",
                'total_net_profit' => "{$index}.00",
            ]);

            if ($index === 1) {
                $sameDateFirst = $transaction;
            }

            if ($index === 2) {
                $sameDateSecond = $transaction;
            }
        }

        $this->actingAs($this->user())
            ->get(route('dashboard'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('recentTransactions', 8)
                    ->where(
                        'recentTransactions.0.id',
                        $sameDateSecond?->id
                    )
                    ->where(
                        'recentTransactions.1.id',
                        $sameDateFirst?->id
                    )
                    ->has(
                        'recentTransactions.0',
                        fn (Assert $row) => $row
                            ->hasAll([
                                'id',
                                'transaction_number',
                                'transaction_date',
                                'status',
                                'total_revenue',
                                'total_net_profit',
                                'customer.id',
                                'customer.name',
                            ])
                            ->missing('items')
                            ->missing('products')
                            ->missing('distributions')
                            ->missing('total_production_cost')
                    )
            );
    }

    public function test_failed_and_pending_lists_are_bounded_and_ordered(): void
    {
        $base = CarbonImmutable::parse('2026-07-24 10:00:00');
        $failed = [];

        foreach (range(1, 6) as $index) {
            $transaction = $this->createTransaction(attributes: [
                'transaction_number' => "TRX-FAILED-{$index}",
                'status' => TransactionStatus::DistributionFailed,
                'failure_reason' => "Sanitized failure {$index}.",
            ]);
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update(['updated_at' => $base->addMinutes($index)]);
            $failed[] = $transaction;
        }

        $pending = [];

        foreach (range(1, 6) as $index) {
            $pending[] = $this->createTransaction(attributes: [
                'transaction_number' => "TRX-PENDING-{$index}",
                'status' => TransactionStatus::PendingDistribution,
                'completed_at' => $base->addMinutes($index),
            ]);
        }

        $overview = app(DashboardOverviewQuery::class)->get();

        $this->assertCount(
            5,
            $overview['recentFailedDistributions']
        );
        $this->assertSame(
            $failed[5]->id,
            $overview['recentFailedDistributions'][0]['id']
        );
        $this->assertSame(
            'Sanitized failure 6.',
            $overview['recentFailedDistributions'][0]['failure_reason']
        );
        $this->assertCount(
            5,
            $overview['recentPendingDistributions']
        );
        $this->assertSame(
            $pending[0]->id,
            $overview['recentPendingDistributions'][0]['id']
        );
        $this->assertSame(
            $pending[4]->id,
            $overview['recentPendingDistributions'][4]['id']
        );
    }

    public function test_dashboard_query_count_is_bounded_and_avoids_item_and_allocation_graphs(): void
    {
        $recipient = $this->createCompanyRecipient();

        foreach (range(1, 12) as $index) {
            $transaction = $this->createTransaction(attributes: [
                'status' => TransactionStatus::Distributed,
                'transaction_date' => '2026-07-24',
            ]);

            if ($index <= 3) {
                $this->distribution(
                    $transaction,
                    $recipient,
                    "{$index}.00"
                );
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->actingAs($this->user())
                ->get(route('dashboard'))
                ->assertOk();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $this->assertLessThanOrEqual(8, count($queries));
        $sql = implode(
            "\n",
            array_column($queries, 'query')
        );
        $this->assertStringNotContainsString(
            'transaction_items',
            $sql
        );
        $this->assertStringNotContainsString(
            'profit_scheme_allocations',
            $sql
        );
    }

    public function test_soft_deleted_customer_remains_visible_in_dashboard_summaries(): void
    {
        $customer = $this->createCustomer([
            'name' => 'Historical Customer',
        ]);
        $transaction = $this->createTransaction(
            customer: $customer,
            attributes: [
                'status' => TransactionStatus::Distributed,
            ]
        );
        $customer->delete();

        $overview = app(DashboardOverviewQuery::class)->get();

        $this->assertSame(
            $transaction->id,
            $overview['recentTransactions'][0]['id']
        );
        $this->assertSame(
            'Historical Customer',
            $overview['recentTransactions'][0]['customer']['name']
        );
    }
}
