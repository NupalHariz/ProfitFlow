<?php

namespace Tests\Feature\Services\ProfitSharing;

use App\Enums\TransactionStatus;
use App\Models\ProfitRecipient;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\ProfitSharing\FixedDecimal;
use App\Services\ProfitSharing\ProfitDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainRecords;
use Tests\TestCase;

abstract class ProfitDistributionTestCase extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', config('database.default'));
        $this->actor = $this->createUser();
    }

    /**
     * @param  list<string>  $percentages
     * @param  array<string, mixed>  $versionAttributes
     * @param  list<ProfitRecipient>|null  $recipients
     * @return array{
     *     version: ProfitSchemeVersion,
     *     recipients: list<ProfitRecipient>
     * }
     */
    protected function createFrozenVersion(
        array $percentages = ['100.0000'],
        array $versionAttributes = [],
        ?array $recipients = null,
        ?ProfitScheme $scheme = null
    ): array {
        $scheme ??= $this->createScheme($this->actor);
        $version = $this->createSchemeVersion(
            $scheme,
            $this->actor,
            array_merge([
                'minimum_profit' => null,
                'maximum_profit' => null,
                'effective_from' => '2026-01-01',
                'effective_until' => null,
                'is_active' => true,
                'published_at' => '2026-07-01 00:00:00',
            ], $versionAttributes)
        );
        $recipients ??= [];

        foreach ($percentages as $index => $percentage) {
            $recipient = $recipients[$index]
                ?? $this->createInvestorRecipient();
            $recipients[$index] = $recipient;
            $this->createAllocation($version, $recipient, [
                'percentage' => $percentage,
            ]);
        }

        return [
            'version' => $version,
            'recipients' => array_values($recipients),
        ];
    }

    /**
     * @param  list<array{
     *     profit: string,
     *     version?: ProfitSchemeVersion|null
     * }>  $itemSpecs
     */
    protected function createPendingTransaction(
        array $itemSpecs
    ): Transaction {
        $transaction = $this->createTransaction(
            creator: $this->actor,
            attributes: [
                'status' => TransactionStatus::PendingDistribution,
                'completed_by_user_id' => $this->actor->id,
                'completed_at' => '2026-07-23 09:00:00',
                'distribution_started_at' => null,
                'distributed_at' => null,
                'failure_reason' => null,
            ]
        );
        $totalRevenue = 0;
        $totalCost = 0;
        $totalProfit = 0;

        foreach ($itemSpecs as $spec) {
            $profit = FixedDecimal::moneyToMinorUnits($spec['profit']);
            $revenue = max($profit, 0);
            $cost = max(-$profit, 0);
            $totalRevenue = FixedDecimal::addMoney(
                $totalRevenue,
                $revenue
            );
            $totalCost = FixedDecimal::addMoney($totalCost, $cost);
            $totalProfit = FixedDecimal::addMoney(
                $totalProfit,
                $profit
            );

            $this->createTransactionItem(
                $transaction,
                version: $spec['version'] ?? null,
                attributes: [
                    'quantity' => 1,
                    'unit_selling_price' => FixedDecimal::formatMoney(
                        $revenue
                    ),
                    'unit_production_cost' => FixedDecimal::formatMoney(
                        $cost
                    ),
                    'total_revenue' => FixedDecimal::formatMoney(
                        $revenue
                    ),
                    'total_production_cost' => FixedDecimal::formatMoney(
                        $cost
                    ),
                    'net_profit' => FixedDecimal::formatMoney($profit),
                    'rule_resolved_at' => ($spec['version'] ?? null)
                        ? '2026-07-23 09:00:00'
                        : null,
                ]
            );
        }

        $transaction->update([
            'total_revenue' => FixedDecimal::formatMoney($totalRevenue),
            'total_production_cost' => FixedDecimal::formatMoney(
                $totalCost
            ),
            'total_net_profit' => FixedDecimal::formatMoney($totalProfit),
        ]);

        return $transaction->refresh();
    }

    protected function distribute(Transaction $transaction): Transaction
    {
        return app(ProfitDistributor::class)->distribute(
            (int) $transaction->getKey()
        );
    }

    /**
     * @return list<TransactionItem>
     */
    protected function orderedItems(Transaction $transaction): array
    {
        return $transaction->items()
            ->orderBy('id')
            ->get()
            ->all();
    }
}
