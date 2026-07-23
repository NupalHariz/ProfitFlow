<?php

namespace App\Services\ProfitSharing;

final readonly class ItemProfitResult
{
    public function __construct(
        public string $totalRevenue,
        public string $totalProductionCost,
        public string $netProfit,
    ) {}

    /**
     * @return array{
     *     total_revenue: string,
     *     total_production_cost: string,
     *     net_profit: string
     * }
     */
    public function toArray(): array
    {
        return [
            'total_revenue' => $this->totalRevenue,
            'total_production_cost' => $this->totalProductionCost,
            'net_profit' => $this->netProfit,
        ];
    }
}
