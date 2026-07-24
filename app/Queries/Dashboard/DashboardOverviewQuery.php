<?php

namespace App\Queries\Dashboard;

use App\Enums\ProfitRecipientType;
use App\Enums\TransactionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DashboardOverviewQuery
{
    private const RECENT_TRANSACTION_LIMIT = 8;

    private const ATTENTION_LIMIT = 5;

    private const TOP_RECIPIENT_LIMIT = 5;

    /**
     * @return array{
     *     financialSummary: array{
     *         totalRevenue: string,
     *         totalProductionCost: string,
     *         totalNetProfit: string,
     *         totalDistributedProfit: string,
     *         totalTransactions: int
     *     },
     *     statusSummary: array<string, int>,
     *     recentTransactions: list<array<string, mixed>>,
     *     recentFailedDistributions: list<array<string, mixed>>,
     *     recentPendingDistributions: list<array<string, mixed>>,
     *     topRecipients: list<array<string, mixed>>
     * }
     */
    public function get(): array
    {
        return [
            'financialSummary' => $this->financialSummary(),
            'statusSummary' => $this->statusSummary(),
            'recentTransactions' => $this->recentTransactions(),
            'recentFailedDistributions' => $this
                ->recentFailedDistributions(),
            'recentPendingDistributions' => $this
                ->recentPendingDistributions(),
            'topRecipients' => $this->topRecipients(),
        ];
    }

    /**
     * @return array{
     *     totalRevenue: string,
     *     totalProductionCost: string,
     *     totalNetProfit: string,
     *     totalDistributedProfit: string,
     *     totalTransactions: int
     * }
     */
    private function financialSummary(): array
    {
        $finalizedStatuses = [
            TransactionStatus::PendingDistribution->value,
            TransactionStatus::ProcessingDistribution->value,
            TransactionStatus::Distributed->value,
            TransactionStatus::DistributionFailed->value,
        ];
        $placeholders = implode(
            ', ',
            array_fill(0, count($finalizedStatuses), '?')
        );
        $financials = DB::table('transactions')
            ->selectRaw(
                'COALESCE(SUM(total_revenue) FILTER '
                ."(WHERE status IN ({$placeholders})), 0)"
                .'::numeric(18, 2) AS total_revenue',
                $finalizedStatuses
            )
            ->selectRaw(
                'COALESCE(SUM(total_production_cost) FILTER '
                ."(WHERE status IN ({$placeholders})), 0)"
                .'::numeric(18, 2) AS total_production_cost',
                $finalizedStatuses
            )
            ->selectRaw(
                'COALESCE(SUM(total_net_profit) FILTER '
                ."(WHERE status IN ({$placeholders})), 0)"
                .'::numeric(18, 2) AS total_net_profit',
                $finalizedStatuses
            )
            ->selectRaw('COUNT(*) AS total_transactions')
            ->first();

        if (! is_object($financials)) {
            throw new LogicException(
                'Dashboard financial aggregation returned no row.'
            );
        }

        $distributedTotal = DB::table('profit_distributions')
            ->selectRaw(
                'COALESCE(SUM(amount), 0)::numeric(18, 2) AS total'
            )
            ->value('total');

        return [
            'totalRevenue' => $this->decimal(
                $financials,
                'total_revenue'
            ),
            'totalProductionCost' => $this->decimal(
                $financials,
                'total_production_cost'
            ),
            'totalNetProfit' => $this->decimal(
                $financials,
                'total_net_profit'
            ),
            'totalDistributedProfit' => $this->decimalValue(
                $distributedTotal
            ),
            'totalTransactions' => (int) $financials
                ->total_transactions,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function statusSummary(): array
    {
        $counts = [];

        foreach (TransactionStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        $rows = DB::table('transactions')
            ->select(['status'])
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            if (! is_string($row->status)
                || ! array_key_exists($row->status, $counts)) {
                throw new LogicException(
                    'Dashboard status aggregation returned an invalid status.'
                );
            }

            $counts[$row->status] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return list<array{
     *     id: int,
     *     transaction_number: string,
     *     transaction_date: string,
     *     status: string,
     *     total_revenue: string,
     *     total_net_profit: string,
     *     customer: array{id: int, name: string}
     * }>
     */
    private function recentTransactions(): array
    {
        return array_values(
            $this->transactionSummaryQuery()
                ->orderByDesc('transactions.transaction_date')
                ->orderByDesc('transactions.id')
                ->limit(self::RECENT_TRANSACTION_LIMIT)
                ->get()
                ->map(
                    fn (object $row): array => [
                        ...$this->transactionIdentity($row),
                        'status' => $this->status($row)->value,
                        'total_revenue' => $this->decimal(
                            $row,
                            'total_revenue'
                        ),
                        'total_net_profit' => $this->decimal(
                            $row,
                            'total_net_profit'
                        ),
                        'customer' => $this->customerSummary($row),
                    ]
                )
                ->values()
                ->all()
        );
    }

    /**
     * @return list<array{
     *     id: int,
     *     transaction_number: string,
     *     transaction_date: string,
     *     failure_reason: string|null,
     *     updated_at: string,
     *     customer: array{id: int, name: string}
     * }>
     */
    private function recentFailedDistributions(): array
    {
        return array_values(
            DB::table('transactions')
                ->join(
                    'customers',
                    'customers.id',
                    '=',
                    'transactions.customer_id'
                )
                ->select([
                    'transactions.id',
                    'transactions.transaction_number',
                    'transactions.transaction_date',
                    'transactions.failure_reason',
                    'transactions.updated_at',
                    'customers.id as customer_id',
                    'customers.name as customer_name',
                ])
                ->where(
                    'transactions.status',
                    TransactionStatus::DistributionFailed->value
                )
                ->orderByDesc('transactions.updated_at')
                ->orderByDesc('transactions.id')
                ->limit(self::ATTENTION_LIMIT)
                ->get()
                ->map(
                    fn (object $row): array => [
                        ...$this->transactionIdentity($row),
                        'failure_reason' => $this->nullableString(
                            $row,
                            'failure_reason'
                        ),
                        'updated_at' => $this->dateTime(
                            $row,
                            'updated_at'
                        ),
                        'customer' => $this->customerSummary($row),
                    ]
                )
                ->values()
                ->all()
        );
    }

    /**
     * @return list<array{
     *     id: int,
     *     transaction_number: string,
     *     completed_at: string|null,
     *     customer: array{id: int, name: string}
     * }>
     */
    private function recentPendingDistributions(): array
    {
        return array_values(
            DB::table('transactions')
                ->join(
                    'customers',
                    'customers.id',
                    '=',
                    'transactions.customer_id'
                )
                ->select([
                    'transactions.id',
                    'transactions.transaction_number',
                    'transactions.completed_at',
                    'customers.id as customer_id',
                    'customers.name as customer_name',
                ])
                ->where(
                    'transactions.status',
                    TransactionStatus::PendingDistribution->value
                )
                ->orderBy('transactions.completed_at')
                ->orderBy('transactions.id')
                ->limit(self::ATTENTION_LIMIT)
                ->get()
                ->map(
                    fn (object $row): array => [
                        'id' => $this->integer($row, 'id'),
                        'transaction_number' => $this->string(
                            $row,
                            'transaction_number'
                        ),
                        'completed_at' => $this->nullableDateTime(
                            $row,
                            'completed_at'
                        ),
                        'customer' => $this->customerSummary($row),
                    ]
                )
                ->values()
                ->all()
        );
    }

    /**
     * @return list<array{
     *     id: int,
     *     display_name: string,
     *     type: string,
     *     total_amount: string,
     *     contributing_transaction_count: int
     * }>
     */
    private function topRecipients(): array
    {
        return array_values(
            DB::table('profit_distributions')
                ->join(
                    'profit_recipients',
                    'profit_recipients.id',
                    '=',
                    'profit_distributions.profit_recipient_id'
                )
                ->select([
                    'profit_recipients.id',
                    'profit_recipients.display_name',
                    'profit_recipients.type',
                ])
                ->selectRaw(
                    'SUM(profit_distributions.amount)'
                    .'::numeric(18, 2) AS total_amount'
                )
                ->selectRaw(
                    'COUNT(DISTINCT profit_distributions.transaction_id) '
                    .'AS contributing_transaction_count'
                )
                ->groupBy([
                    'profit_recipients.id',
                    'profit_recipients.display_name',
                    'profit_recipients.type',
                ])
                ->orderByRaw('SUM(profit_distributions.amount) DESC')
                ->orderBy('profit_recipients.display_name')
                ->orderBy('profit_recipients.id')
                ->limit(self::TOP_RECIPIENT_LIMIT)
                ->get()
                ->map(
                    function (object $row): array {
                        $type = ProfitRecipientType::tryFrom(
                            $this->string($row, 'type')
                        );

                        if ($type === null) {
                            throw new LogicException(
                                'Dashboard recipient aggregation returned '
                                .'an invalid recipient type.'
                            );
                        }

                        return [
                            'id' => $this->integer($row, 'id'),
                            'display_name' => $this->string(
                                $row,
                                'display_name'
                            ),
                            'type' => $type->value,
                            'total_amount' => $this->decimal(
                                $row,
                                'total_amount'
                            ),
                            'contributing_transaction_count' => $this
                                ->integer(
                                    $row,
                                    'contributing_transaction_count'
                                ),
                        ];
                    }
                )
                ->values()
                ->all()
        );
    }

    private function transactionSummaryQuery(): Builder
    {
        return DB::table('transactions')
            ->join(
                'customers',
                'customers.id',
                '=',
                'transactions.customer_id'
            )
            ->select([
                'transactions.id',
                'transactions.transaction_number',
                'transactions.transaction_date',
                'transactions.status',
                'transactions.total_revenue',
                'transactions.total_net_profit',
                'customers.id as customer_id',
                'customers.name as customer_name',
            ]);
    }

    /**
     * @return array{
     *     id: int,
     *     transaction_number: string,
     *     transaction_date: string
     * }
     */
    private function transactionIdentity(object $row): array
    {
        return [
            'id' => $this->integer($row, 'id'),
            'transaction_number' => $this->string(
                $row,
                'transaction_number'
            ),
            'transaction_date' => $this->string(
                $row,
                'transaction_date'
            ),
        ];
    }

    /**
     * @return array{id: int, name: string}
     */
    private function customerSummary(object $row): array
    {
        return [
            'id' => $this->integer($row, 'customer_id'),
            'name' => $this->string($row, 'customer_name'),
        ];
    }

    private function status(object $row): TransactionStatus
    {
        $status = TransactionStatus::tryFrom(
            $this->string($row, 'status')
        );

        if ($status === null) {
            throw new LogicException(
                'Dashboard transaction query returned an invalid status.'
            );
        }

        return $status;
    }

    private function decimal(object $row, string $field): string
    {
        return $this->decimalValue($row->{$field} ?? null);
    }

    private function decimalValue(mixed $value): string
    {
        if (! is_string($value)) {
            throw new LogicException(
                'Dashboard aggregate must return a decimal string.'
            );
        }

        return $value;
    }

    private function string(object $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new LogicException(
                "Dashboard field [{$field}] must be a string."
            );
        }

        return $value;
    }

    private function integer(object $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new LogicException(
            "Dashboard field [{$field}] must be an integer."
        );
    }

    private function nullableString(
        object $row,
        string $field
    ): ?string {
        $value = $row->{$field} ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new LogicException(
                "Dashboard field [{$field}] must be a nullable string."
            );
        }

        return $value;
    }

    private function dateTime(object $row, string $field): string
    {
        return CarbonImmutable::parse(
            $this->string($row, $field)
        )->toISOString();
    }

    private function nullableDateTime(
        object $row,
        string $field
    ): ?string {
        $value = $this->nullableString($row, $field);

        return $value === null
            ? null
            : CarbonImmutable::parse($value)->toISOString();
    }
}
