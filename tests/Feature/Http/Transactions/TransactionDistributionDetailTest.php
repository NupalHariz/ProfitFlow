<?php

namespace Tests\Feature\Http\Transactions;

use App\Enums\TransactionStatus;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class TransactionDistributionDetailTest extends TransactionHttpTestCase
{
    #[DataProvider('lifecycleCapabilities')]
    public function test_detail_exposes_only_valid_lifecycle_capabilities(
        TransactionStatus $status,
        bool $canEdit,
        bool $canComplete,
        bool $canRetry,
        bool $canRedrive
    ): void {
        $user = $this->user();
        $transaction = $this->createTransaction(
            creator: $user,
            attributes: [
                'status' => $status,
                'completed_by_user_id' => $status
                    === TransactionStatus::Draft
                    ? null
                    : $user->id,
                'completed_at' => $status
                    === TransactionStatus::Draft
                    ? null
                    : '2026-07-23 09:00:00',
            ]
        );

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('transaction.can_edit', $canEdit)
                    ->where('transaction.can_complete', $canComplete)
                    ->where(
                        'transaction.can_retry_distribution',
                        $canRetry
                    )
                    ->where(
                        'transaction.can_redrive_distribution',
                        $canRedrive
                    )
            );
    }

    /**
     * @return array<string, array{
     *     TransactionStatus,
     *     bool,
     *     bool,
     *     bool,
     *     bool
     * }>
     */
    public static function lifecycleCapabilities(): array
    {
        return [
            'draft' => [
                TransactionStatus::Draft,
                true,
                true,
                false,
                false,
            ],
            'pending redrive' => [
                TransactionStatus::PendingDistribution,
                false,
                false,
                false,
                true,
            ],
            'processing' => [
                TransactionStatus::ProcessingDistribution,
                false,
                false,
                false,
                false,
            ],
            'distributed' => [
                TransactionStatus::Distributed,
                false,
                false,
                false,
                false,
            ],
            'failed retry' => [
                TransactionStatus::DistributionFailed,
                false,
                false,
                true,
                false,
            ],
            'cancelled' => [
                TransactionStatus::Cancelled,
                false,
                false,
                false,
                false,
            ],
        ];
    }

    public function test_detail_uses_frozen_rule_item_and_recipient_snapshots_with_deterministic_rows(): void
    {
        $user = $this->user();
        $category = $this->createCategory([
            'name' => 'Original Category',
        ]);
        $product = $this->createProduct($category, [
            'name' => 'Original Product',
            'sku' => 'ORIGINAL-SKU',
        ]);
        $scheme = $this->createScheme($user, [
            'name' => 'Frozen Scheme',
        ]);
        $version = $this->publishedVersion($scheme, $user, 1);
        $first = $this->createInvestorRecipient(attributes: [
            'display_name' => 'Original Alpha',
        ]);
        $second = $this->createCompanyRecipient([
            'display_name' => 'Original Company',
        ]);
        $transaction = $this->distributedTransaction($user, [
            'total_net_profit' => '100.00',
        ]);
        $item = $this->createTransactionItem(
            $transaction,
            $product,
            $version,
            [
                'product_name_snapshot' => 'Original Product',
                'product_sku_snapshot' => 'ORIGINAL-SKU',
                'category_name_snapshot' => 'Original Category',
                'net_profit' => '100.00',
                'rule_resolved_at' => '2026-07-23 09:00:00',
            ]
        );
        $firstRow = $this->createDistribution(
            $item,
            $version,
            $first,
            ['percentage' => '60.0000', 'amount' => '60.00']
        );
        $secondRow = $this->createDistribution(
            $item,
            $version,
            $second,
            ['percentage' => '40.0000', 'amount' => '40.00']
        );
        $this->publishedVersion($scheme, $user, 2);
        $first->update(['display_name' => 'Current Renamed Alpha']);
        $second->update(['display_name' => 'Current Renamed Company']);
        $product->update([
            'name' => 'Current Product',
            'sku' => 'CURRENT-SKU',
        ]);
        $category->update(['name' => 'Current Category']);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('transaction.status', 'distributed')
                    ->where(
                        'transaction.completed_at',
                        '2026-07-23T09:00:00+00:00'
                    )
                    ->where(
                        'transaction.distribution_started_at',
                        '2026-07-23T09:01:00+00:00'
                    )
                    ->where(
                        'transaction.distributed_at',
                        '2026-07-23T09:02:00+00:00'
                    )
                    ->where('transaction.completer.id', $user->id)
                    ->where(
                        'transaction.items.0.product_name_snapshot',
                        'Original Product'
                    )
                    ->where(
                        'transaction.items.0.product_sku_snapshot',
                        'ORIGINAL-SKU'
                    )
                    ->where(
                        'transaction.items.0.category_name_snapshot',
                        'Original Category'
                    )
                    ->where(
                        'transaction.items.0.applied_profit_scheme_version_id',
                        $version->id
                    )
                    ->where(
                        'transaction.items.0.applied_rule.profit_scheme_version_id',
                        $version->id
                    )
                    ->where(
                        'transaction.items.0.applied_rule.version',
                        1
                    )
                    ->where(
                        'transaction.items.0.applied_rule.scheme.name',
                        'Frozen Scheme'
                    )
                    ->missing(
                        'transaction.items.0.applied_rule.allocations'
                    )
                    ->where(
                        'transaction.items.0.distributed_total',
                        '100.00'
                    )
                    ->has('transaction.items.0.distributions', 2)
                    ->where(
                        'transaction.items.0.distributions.0.id',
                        $firstRow->id
                    )
                    ->where(
                        'transaction.items.0.distributions.0.recipient_name_snapshot',
                        'Original Alpha'
                    )
                    ->where(
                        'transaction.items.0.distributions.0.percentage',
                        '60.0000'
                    )
                    ->where(
                        'transaction.items.0.distributions.0.amount',
                        '60.00'
                    )
                    ->where(
                        'transaction.items.0.distributions.1.id',
                        $secondRow->id
                    )
                    ->where(
                        'transaction.items.0.distributions.1.recipient_name_snapshot',
                        'Original Company'
                    )
                    ->missing('transaction.profit_scheme_versions')
                    ->missing('transaction.recipients')
            );
    }

    public function test_recipient_summary_groups_items_exactly_and_distinguishes_overall_profit(): void
    {
        $user = $this->user();
        $scheme = $this->createScheme($user);
        $version = $this->publishedVersion($scheme, $user, 1);
        $shared = $this->createInvestorRecipient(attributes: [
            'display_name' => 'Shared Investor',
        ]);
        $company = $this->createCompanyRecipient([
            'display_name' => 'Company',
        ]);
        $transaction = $this->distributedTransaction($user, [
            'total_revenue' => '150.00',
            'total_production_cost' => '200.00',
            'total_net_profit' => '-50.00',
        ]);
        $first = $this->createTransactionItem(
            $transaction,
            version: $version,
            attributes: [
                'total_revenue' => '100.00',
                'total_production_cost' => '0.00',
                'net_profit' => '100.00',
                'rule_resolved_at' => '2026-07-23 09:00:00',
            ]
        );
        $second = $this->createTransactionItem(
            $transaction,
            version: $version,
            attributes: [
                'total_revenue' => '50.00',
                'total_production_cost' => '0.00',
                'net_profit' => '50.00',
                'rule_resolved_at' => '2026-07-23 09:00:00',
            ]
        );
        $loss = $this->createTransactionItem(
            $transaction,
            attributes: [
                'total_revenue' => '0.00',
                'total_production_cost' => '200.00',
                'net_profit' => '-200.00',
                'applied_profit_scheme_version_id' => null,
                'rule_resolved_at' => null,
            ]
        );
        $this->createDistribution(
            $first,
            $version,
            $shared,
            ['percentage' => '60.0000', 'amount' => '60.00']
        );
        $this->createDistribution(
            $first,
            $version,
            $company,
            ['percentage' => '40.0000', 'amount' => '40.00']
        );
        $this->createDistribution(
            $second,
            $version,
            $shared,
            ['percentage' => '60.0000', 'amount' => '30.00']
        );
        $this->createDistribution(
            $second,
            $version,
            $company,
            ['percentage' => '40.0000', 'amount' => '20.00']
        );

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('transaction.total_net_profit', '-50.00')
                    ->where('distributedTotal', '150.00')
                    ->has('recipientSummary', 2)
                    ->where(
                        'recipientSummary.0.profit_recipient_id',
                        $shared->id
                    )
                    ->where(
                        'recipientSummary.0.recipient_name_snapshot',
                        'Shared Investor'
                    )
                    ->where(
                        'recipientSummary.0.total_amount',
                        '90.00'
                    )
                    ->where(
                        'recipientSummary.0.contributing_item_count',
                        2
                    )
                    ->where(
                        'recipientSummary.1.profit_recipient_id',
                        $company->id
                    )
                    ->where(
                        'recipientSummary.1.total_amount',
                        '60.00'
                    )
                    ->where(
                        'recipientSummary.1.contributing_item_count',
                        2
                    )
                    ->where(
                        'transaction.items.0.distributed_total',
                        '100.00'
                    )
                    ->where(
                        'transaction.items.1.distributed_total',
                        '50.00'
                    )
                    ->where(
                        'transaction.items.2.id',
                        $loss->id
                    )
                    ->where(
                        'transaction.items.2.applied_rule',
                        null
                    )
                    ->where(
                        'transaction.items.2.distributed_total',
                        '0.00'
                    )
                    ->has('transaction.items.2.distributions', 0)
            );
    }

    public function test_recipient_summary_tie_order_is_name_then_recipient_id(): void
    {
        $user = $this->user();
        $scheme = $this->createScheme($user);
        $version = $this->publishedVersion($scheme, $user, 1);
        $beta = $this->createCompanyRecipient([
            'display_name' => 'Beta Recipient',
        ]);
        $alpha = $this->createInvestorRecipient(attributes: [
            'display_name' => 'Alpha Recipient',
        ]);
        $transaction = $this->distributedTransaction($user, [
            'total_net_profit' => '100.00',
        ]);
        $item = $this->createTransactionItem(
            $transaction,
            version: $version,
            attributes: ['net_profit' => '100.00']
        );
        $this->createDistribution(
            $item,
            $version,
            $beta,
            ['percentage' => '50.0000', 'amount' => '50.00']
        );
        $this->createDistribution(
            $item,
            $version,
            $alpha,
            ['percentage' => '50.0000', 'amount' => '50.00']
        );

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'recipientSummary.0.recipient_name_snapshot',
                        'Alpha Recipient'
                    )
                    ->where(
                        'recipientSummary.1.recipient_name_snapshot',
                        'Beta Recipient'
                    )
            );
    }

    public function test_all_loss_distributed_transaction_has_empty_successful_ledger_summary(): void
    {
        $user = $this->user();
        $transaction = $this->distributedTransaction($user, [
            'total_revenue' => '0.00',
            'total_production_cost' => '25.00',
            'total_net_profit' => '-25.00',
        ]);
        $this->createTransactionItem($transaction, attributes: [
            'total_revenue' => '0.00',
            'total_production_cost' => '25.00',
            'net_profit' => '-25.00',
            'applied_profit_scheme_version_id' => null,
            'rule_resolved_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('recipientSummary', 0)
                    ->where('distributedTotal', '0.00')
                    ->where(
                        'transaction.items.0.applied_rule',
                        null
                    )
                    ->has('transaction.items.0.distributions', 0)
            );
    }

    public function test_detail_query_count_is_bounded_with_frozen_rules_and_distributions(): void
    {
        $user = $this->user();
        $scheme = $this->createScheme($user);
        $version = $this->publishedVersion($scheme, $user, 1);
        $recipient = $this->createCompanyRecipient();
        $transaction = $this->distributedTransaction($user, [
            'total_net_profit' => '200.00',
        ]);

        foreach (range(1, 20) as $index) {
            $item = $this->createTransactionItem(
                $transaction,
                version: $version,
                attributes: [
                    'product_sku_snapshot' => "QUERY-{$index}",
                    'net_profit' => '10.00',
                    'rule_resolved_at' => '2026-07-23 09:00:00',
                ]
            );
            $this->createDistribution(
                $item,
                $version,
                $recipient,
                ['amount' => '10.00']
            );
        }
        $this->actingAs($user);
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->get(route('transactions.show', $transaction))
                ->assertOk();
            $queryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame(
            20,
            TransactionItem::query()
                ->where('transaction_id', $transaction->id)
                ->count()
        );
        $this->assertLessThanOrEqual(12, $queryCount);
    }

    private function publishedVersion(
        ProfitScheme $scheme,
        User $user,
        int $number
    ): ProfitSchemeVersion {
        return $this->createSchemeVersion($scheme, $user, [
            'version' => $number,
            'minimum_profit' => null,
            'maximum_profit' => null,
            'effective_from' => '2026-01-01',
            'effective_until' => null,
            'published_at' => '2026-07-01 00:00:00',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function distributedTransaction(
        User $user,
        array $attributes = []
    ): Transaction {
        return $this->createTransaction(
            creator: $user,
            attributes: array_merge([
                'status' => TransactionStatus::Distributed,
                'completed_by_user_id' => $user->id,
                'completed_at' => '2026-07-23 09:00:00',
                'distribution_started_at' => '2026-07-23 09:01:00',
                'distributed_at' => '2026-07-23 09:02:00',
                'failure_reason' => null,
            ], $attributes)
        );
    }
}
