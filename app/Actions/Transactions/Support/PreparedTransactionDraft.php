<?php

namespace App\Actions\Transactions\Support;

use App\Services\ProfitSharing\TransactionProfitTotals;

final readonly class PreparedTransactionDraft
{
    /**
     * @param  list<array{
     *     product_id: int,
     *     product_category_id: int,
     *     applied_profit_scheme_version_id: null,
     *     product_name_snapshot: string,
     *     product_sku_snapshot: string,
     *     category_name_snapshot: string,
     *     quantity: int,
     *     unit_selling_price: string,
     *     unit_production_cost: string,
     *     total_revenue: string,
     *     total_production_cost: string,
     *     net_profit: string,
     *     rule_resolved_at: null
     * }>  $items
     */
    public function __construct(
        public int $customerId,
        public string $transactionDate,
        public array $items,
        public TransactionProfitTotals $totals,
    ) {}
}
