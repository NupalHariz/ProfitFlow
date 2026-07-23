<?php

namespace App\Services\ProfitSharing;

final readonly class TransactionProfitTotals
{
    public function __construct(
        public string $totalRevenue,
        public string $totalProductionCost,
        public string $totalNetProfit,
    ) {}

    /**
     * @return array{
     *     total_revenue: string,
     *     total_production_cost: string,
     *     total_net_profit: string
     * }
     */
    public function toArray(): array
    {
        return [
            'total_revenue' => $this->totalRevenue,
            'total_production_cost' => $this->totalProductionCost,
            'total_net_profit' => $this->totalNetProfit,
        ];
    }
}
