<?php

namespace Tests\Feature\Http\Dashboard;

use App\Enums\TransactionStatus;
use App\Queries\Dashboard\DashboardOverviewQuery;

class DashboardFinancialSummaryTest extends DashboardTestCase
{
    public function test_empty_database_returns_zero_safe_financial_and_status_summaries(): void
    {
        $overview = app(DashboardOverviewQuery::class)->get();

        $this->assertSame([
            'totalRevenue' => '0.00',
            'totalProductionCost' => '0.00',
            'totalNetProfit' => '0.00',
            'totalDistributedProfit' => '0.00',
            'totalTransactions' => 0,
        ], $overview['financialSummary']);
        $this->assertSame([
            'draft' => 0,
            'pending_distribution' => 0,
            'processing_distribution' => 0,
            'distributed' => 0,
            'distribution_failed' => 0,
            'cancelled' => 0,
        ], $overview['statusSummary']);
    }

    public function test_financial_summary_uses_only_finalized_non_cancelled_transactions(): void
    {
        $transactions = [
            [TransactionStatus::Draft, '100.00', '40.00', '60.00'],
            [TransactionStatus::Cancelled, '200.00', '50.00', '150.00'],
            [
                TransactionStatus::PendingDistribution,
                '300.10',
                '100.00',
                '200.10',
            ],
            [
                TransactionStatus::ProcessingDistribution,
                '400.20',
                '500.00',
                '-99.80',
            ],
            [
                TransactionStatus::Distributed,
                '500.30',
                '200.00',
                '300.30',
            ],
            [
                TransactionStatus::DistributionFailed,
                '600.40',
                '100.00',
                '500.40',
            ],
        ];

        foreach ($transactions as [$status, $revenue, $cost, $profit]) {
            $this->createTransaction(attributes: [
                'status' => $status,
                'total_revenue' => $revenue,
                'total_production_cost' => $cost,
                'total_net_profit' => $profit,
            ]);
        }

        $summary = app(DashboardOverviewQuery::class)
            ->get()['financialSummary'];

        $this->assertSame('1801.00', $summary['totalRevenue']);
        $this->assertSame(
            '900.00',
            $summary['totalProductionCost']
        );
        $this->assertSame('901.00', $summary['totalNetProfit']);
        $this->assertSame(6, $summary['totalTransactions']);
    }

    public function test_total_net_profit_may_be_negative(): void
    {
        $this->createTransaction(attributes: [
            'status' => TransactionStatus::Distributed,
            'total_revenue' => '25.00',
            'total_production_cost' => '100.00',
            'total_net_profit' => '-75.00',
        ]);

        $summary = app(DashboardOverviewQuery::class)
            ->get()['financialSummary'];

        $this->assertSame('-75.00', $summary['totalNetProfit']);
    }

    public function test_distributed_profit_comes_from_ledger_and_can_exceed_overall_net_profit(): void
    {
        $transaction = $this->createTransaction(attributes: [
            'status' => TransactionStatus::Distributed,
            'total_revenue' => '1000.00',
            'total_production_cost' => '600.00',
            'total_net_profit' => '400.00',
        ]);
        $recipient = $this->createInvestorRecipient();
        $this->distribution($transaction, $recipient, '600.00');
        $this->createTransactionItem($transaction, attributes: [
            'quantity' => 1,
            'unit_selling_price' => '100.00',
            'unit_production_cost' => '300.00',
            'total_revenue' => '100.00',
            'total_production_cost' => '300.00',
            'net_profit' => '-200.00',
        ]);

        $summary = app(DashboardOverviewQuery::class)
            ->get()['financialSummary'];

        $this->assertSame('400.00', $summary['totalNetProfit']);
        $this->assertSame(
            '600.00',
            $summary['totalDistributedProfit']
        );
    }

    public function test_all_loss_distributed_transaction_adds_no_distributed_profit(): void
    {
        $transaction = $this->createTransaction(attributes: [
            'status' => TransactionStatus::Distributed,
            'total_revenue' => '100.00',
            'total_production_cost' => '300.00',
            'total_net_profit' => '-200.00',
        ]);
        $this->createTransactionItem($transaction, attributes: [
            'quantity' => 1,
            'unit_selling_price' => '100.00',
            'unit_production_cost' => '300.00',
            'total_revenue' => '100.00',
            'total_production_cost' => '300.00',
            'net_profit' => '-200.00',
        ]);

        $summary = app(DashboardOverviewQuery::class)
            ->get()['financialSummary'];

        $this->assertSame('-200.00', $summary['totalNetProfit']);
        $this->assertSame(
            '0.00',
            $summary['totalDistributedProfit']
        );
    }

    public function test_status_summary_contains_every_enum_status_in_lifecycle_order(): void
    {
        foreach (TransactionStatus::cases() as $index => $status) {
            $count = $index === 0 ? 2 : 1;

            for ($created = 0; $created < $count; $created++) {
                $this->createTransaction(attributes: [
                    'status' => $status,
                ]);
            }
        }

        $summary = app(DashboardOverviewQuery::class)
            ->get()['statusSummary'];

        $this->assertSame([
            'draft',
            'pending_distribution',
            'processing_distribution',
            'distributed',
            'distribution_failed',
            'cancelled',
        ], array_keys($summary));
        $this->assertSame([
            'draft' => 2,
            'pending_distribution' => 1,
            'processing_distribution' => 1,
            'distributed' => 1,
            'distribution_failed' => 1,
            'cancelled' => 1,
        ], $summary);
    }
}
