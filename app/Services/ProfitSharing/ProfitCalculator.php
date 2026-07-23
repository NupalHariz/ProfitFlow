<?php

namespace App\Services\ProfitSharing;

use InvalidArgumentException;

final class ProfitCalculator
{
    public function calculateItem(
        int $quantity,
        string $unitSellingPrice,
        string $unitProductionCost
    ): ItemProfitResult {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        $sellingPriceInCents = FixedDecimal::moneyToMinorUnits($unitSellingPrice);
        $productionCostInCents = FixedDecimal::moneyToMinorUnits($unitProductionCost);

        if ($sellingPriceInCents < 0) {
            throw new InvalidArgumentException('Unit selling price cannot be negative.');
        }

        if ($productionCostInCents < 0) {
            throw new InvalidArgumentException('Unit production cost cannot be negative.');
        }

        $revenueInCents = FixedDecimal::multiplyMoney(
            $sellingPriceInCents,
            $quantity
        );
        $totalCostInCents = FixedDecimal::multiplyMoney(
            $productionCostInCents,
            $quantity
        );

        return new ItemProfitResult(
            totalRevenue: FixedDecimal::formatMoney($revenueInCents),
            totalProductionCost: FixedDecimal::formatMoney($totalCostInCents),
            netProfit: FixedDecimal::formatMoney(
                $revenueInCents - $totalCostInCents
            ),
        );
    }

    /**
     * @param  iterable<ItemProfitResult>  $items
     */
    public function aggregateItems(iterable $items): TransactionProfitTotals
    {
        $totalRevenueInCents = 0;
        $totalProductionCostInCents = 0;
        $totalNetProfitInCents = 0;

        foreach ($items as $item) {
            $revenueInCents = FixedDecimal::moneyToMinorUnits($item->totalRevenue);
            $productionCostInCents = FixedDecimal::moneyToMinorUnits(
                $item->totalProductionCost
            );
            $netProfitInCents = FixedDecimal::moneyToMinorUnits($item->netProfit);

            if ($revenueInCents < 0 || $productionCostInCents < 0) {
                throw new InvalidArgumentException(
                    'Item revenue and production cost cannot be negative.'
                );
            }

            if ($revenueInCents - $productionCostInCents !== $netProfitInCents) {
                throw new InvalidArgumentException(
                    'Item net profit must equal revenue minus production cost.'
                );
            }

            $totalRevenueInCents = FixedDecimal::addMoney(
                $totalRevenueInCents,
                $revenueInCents
            );
            $totalProductionCostInCents = FixedDecimal::addMoney(
                $totalProductionCostInCents,
                $productionCostInCents
            );
            $totalNetProfitInCents = FixedDecimal::addMoney(
                $totalNetProfitInCents,
                $netProfitInCents
            );
        }

        return new TransactionProfitTotals(
            totalRevenue: FixedDecimal::formatMoney($totalRevenueInCents),
            totalProductionCost: FixedDecimal::formatMoney(
                $totalProductionCostInCents
            ),
            totalNetProfit: FixedDecimal::formatMoney($totalNetProfitInCents),
        );
    }
}
