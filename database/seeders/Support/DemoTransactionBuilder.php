<?php

namespace Database\Seeders\Support;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidProfitDistribution;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\ProfitSharing\FixedDecimal;
use App\Services\ProfitSharing\ItemProfitResult;
use App\Services\ProfitSharing\ProfitCalculator;
use App\Services\ProfitSharing\ProfitDistributionFailureRecorder;
use App\Services\ProfitSharing\ProfitDistributor;
use App\Services\ProfitSharing\ProfitRuleResolver;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DemoTransactionBuilder
{
    public function __construct(
        private readonly ProfitCalculator $calculator,
        private readonly ProfitRuleResolver $ruleResolver,
        private readonly ProfitDistributor $distributor,
        private readonly ProfitDistributionFailureRecorder $failureRecorder
    ) {}

    /**
     * @param  list<array{
     *     sku: string,
     *     quantity: int,
     *     unit_selling_price?: string|null
     * }>  $items
     */
    public function distributed(
        string $number,
        string $customerEmail,
        string $transactionDate,
        array $items,
        CarbonImmutable $createdAt,
        CarbonImmutable $completedAt,
        CarbonImmutable $distributedAt
    ): Transaction {
        return $this->create(
            number: $number,
            customerEmail: $customerEmail,
            transactionDate: $transactionDate,
            items: $items,
            targetStatus: TransactionStatus::Distributed,
            createdAt: $createdAt,
            completedAt: $completedAt,
            distributedAt: $distributedAt
        );
    }

    /**
     * @param  list<array{
     *     sku: string,
     *     quantity: int,
     *     unit_selling_price?: string|null
     * }>  $items
     */
    public function draft(
        string $number,
        string $customerEmail,
        string $transactionDate,
        array $items,
        CarbonImmutable $createdAt
    ): Transaction {
        return $this->create(
            number: $number,
            customerEmail: $customerEmail,
            transactionDate: $transactionDate,
            items: $items,
            targetStatus: TransactionStatus::Draft,
            createdAt: $createdAt
        );
    }

    /**
     * @param  list<array{
     *     sku: string,
     *     quantity: int,
     *     unit_selling_price?: string|null
     * }>  $items
     */
    public function pending(
        string $number,
        string $customerEmail,
        string $transactionDate,
        array $items,
        CarbonImmutable $createdAt,
        CarbonImmutable $completedAt
    ): Transaction {
        return $this->create(
            number: $number,
            customerEmail: $customerEmail,
            transactionDate: $transactionDate,
            items: $items,
            targetStatus: TransactionStatus::PendingDistribution,
            createdAt: $createdAt,
            completedAt: $completedAt
        );
    }

    /**
     * @param  list<array{
     *     sku: string,
     *     quantity: int,
     *     unit_selling_price?: string|null
     * }>  $items
     */
    public function failed(
        string $number,
        string $customerEmail,
        string $transactionDate,
        array $items,
        CarbonImmutable $createdAt,
        CarbonImmutable $completedAt,
        CarbonImmutable $distributionStartedAt,
        CarbonImmutable $failedAt,
        string $failureReason
    ): Transaction {
        return $this->create(
            number: $number,
            customerEmail: $customerEmail,
            transactionDate: $transactionDate,
            items: $items,
            targetStatus: TransactionStatus::DistributionFailed,
            createdAt: $createdAt,
            completedAt: $completedAt,
            distributionStartedAt: $distributionStartedAt,
            failedAt: $failedAt,
            failureReason: $failureReason
        );
    }

    /**
     * @param  list<array{
     *     sku: string,
     *     quantity: int,
     *     unit_selling_price?: string|null
     * }>  $items
     */
    public function cancelled(
        string $number,
        string $customerEmail,
        string $transactionDate,
        array $items,
        CarbonImmutable $createdAt,
        CarbonImmutable $cancelledAt
    ): Transaction {
        return $this->create(
            number: $number,
            customerEmail: $customerEmail,
            transactionDate: $transactionDate,
            items: $items,
            targetStatus: TransactionStatus::Cancelled,
            createdAt: $createdAt,
            cancelledAt: $cancelledAt
        );
    }

    /**
     * @param  list<array{
     *     sku: string,
     *     quantity: int,
     *     unit_selling_price?: string|null
     * }>  $items
     */
    private function create(
        string $number,
        string $customerEmail,
        string $transactionDate,
        array $items,
        TransactionStatus $targetStatus,
        CarbonImmutable $createdAt,
        ?CarbonImmutable $completedAt = null,
        ?CarbonImmutable $distributionStartedAt = null,
        ?CarbonImmutable $distributedAt = null,
        ?CarbonImmutable $failedAt = null,
        ?CarbonImmutable $cancelledAt = null,
        ?string $failureReason = null
    ): Transaction {
        $existing = Transaction::query()
            ->where('transaction_number', $number)
            ->first();

        if ($existing instanceof Transaction) {
            return $existing;
        }

        $transaction = $this->at(
            $createdAt,
            fn (): Transaction => DB::transaction(
                fn (): Transaction => $this->persistTransaction(
                    number: $number,
                    customerEmail: $customerEmail,
                    transactionDate: $transactionDate,
                    items: $items,
                    targetStatus: $targetStatus,
                    completedAt: $completedAt,
                    distributionStartedAt: $distributionStartedAt,
                    cancelledAt: $cancelledAt
                )
            )
        );

        if ($targetStatus === TransactionStatus::Distributed) {
            if ($distributedAt === null) {
                throw new LogicException(
                    "Distributed demo transaction [{$number}] requires a distribution timestamp."
                );
            }

            return $this->at(
                $distributedAt,
                fn (): Transaction => $this->distributor->distribute(
                    (int) $transaction->getKey()
                )
            );
        }

        if ($targetStatus === TransactionStatus::DistributionFailed) {
            if ($failedAt === null || $failureReason === null) {
                throw new LogicException(
                    "Failed demo transaction [{$number}] requires failure details."
                );
            }

            $failed = $this->at(
                $failedAt,
                fn (): ?Transaction => $this->failureRecorder->record(
                    (int) $transaction->getKey(),
                    new InvalidProfitDistribution($failureReason)
                )
            );

            if (! $failed instanceof Transaction) {
                throw new LogicException(
                    "Failed demo transaction [{$number}] could not be recorded."
                );
            }

            return $failed;
        }

        return $transaction;
    }

    /**
     * @param  list<array{
     *     sku: string,
     *     quantity: int,
     *     unit_selling_price?: string|null
     * }>  $items
     */
    private function persistTransaction(
        string $number,
        string $customerEmail,
        string $transactionDate,
        array $items,
        TransactionStatus $targetStatus,
        ?CarbonImmutable $completedAt,
        ?CarbonImmutable $distributionStartedAt,
        ?CarbonImmutable $cancelledAt
    ): Transaction {
        $admin = User::query()
            ->where('email', 'admin@example.com')
            ->sole();
        $customer = Customer::query()
            ->where('email', $customerEmail)
            ->sole();
        $products = Product::query()
            ->with('category')
            ->whereIn('sku', array_column($items, 'sku'))
            ->get()
            ->keyBy('sku');
        $resolveRules = in_array($targetStatus, [
            TransactionStatus::Distributed,
            TransactionStatus::PendingDistribution,
            TransactionStatus::DistributionFailed,
        ], true);
        /** @var list<ItemProfitResult> $calculations */
        $calculations = [];
        /** @var list<array<string, mixed>> $preparedItems */
        $preparedItems = [];

        foreach ($items as $definition) {
            $product = $products->get($definition['sku']);

            if (! $product instanceof Product) {
                throw new LogicException(
                    "Demo product [{$definition['sku']}] does not exist."
                );
            }

            $category = $product->getRelation('category');

            if (! $category instanceof ProductCategory) {
                throw new LogicException(
                    "Demo product [{$definition['sku']}] has no category."
                );
            }

            $sellingPrice = $definition['unit_selling_price']
                ?? $this->storedMoney($product, 'default_selling_price');
            $productionCost = $this->storedMoney(
                $product,
                'default_production_cost'
            );
            $calculation = $this->calculator->calculateItem(
                $definition['quantity'],
                $sellingPrice,
                $productionCost
            );
            $version = null;

            if ($resolveRules
                && FixedDecimal::moneyToMinorUnits(
                    $calculation->netProfit
                ) > 0) {
                $version = $this->ruleResolver->resolve(
                    $transactionDate,
                    (int) $category->getKey(),
                    $calculation->netProfit
                );

                if (! $version instanceof ProfitSchemeVersion) {
                    throw new LogicException(
                        "Demo transaction [{$number}] has no rule for profitable product [{$definition['sku']}]."
                    );
                }
            }

            $calculations[] = $calculation;
            $preparedItems[] = [
                'product_id' => $product->id,
                'product_category_id' => $category->id,
                'applied_profit_scheme_version_id' => $version?->id,
                'product_name_snapshot' => $product->name,
                'product_sku_snapshot' => $product->sku,
                'category_name_snapshot' => $category->name,
                'quantity' => $definition['quantity'],
                'unit_selling_price' => $sellingPrice,
                'unit_production_cost' => $productionCost,
                ...$calculation->toArray(),
                'rule_resolved_at' => $version === null
                    ? null
                    : $completedAt,
            ];
        }

        $totals = $this->calculator->aggregateItems($calculations);
        $initialStatus = match ($targetStatus) {
            TransactionStatus::Distributed => TransactionStatus::PendingDistribution,
            TransactionStatus::DistributionFailed => TransactionStatus::ProcessingDistribution,
            default => $targetStatus,
        };
        $isFinalized = in_array($targetStatus, [
            TransactionStatus::Distributed,
            TransactionStatus::PendingDistribution,
            TransactionStatus::DistributionFailed,
        ], true);
        $transaction = Transaction::query()->create([
            'transaction_number' => $number,
            'idempotency_key' => null,
            'customer_id' => $customer->id,
            'transaction_date' => $transactionDate,
            'status' => $initialStatus,
            ...$totals->toArray(),
            'failure_reason' => null,
            'created_by_user_id' => $admin->id,
            'completed_by_user_id' => $isFinalized ? $admin->id : null,
            'completed_at' => $isFinalized ? $completedAt : null,
            'distribution_started_at' => $targetStatus === TransactionStatus::DistributionFailed
                ? $distributionStartedAt
                : null,
            'distributed_at' => null,
            'cancelled_at' => $cancelledAt,
        ]);

        foreach ($preparedItems as $preparedItem) {
            TransactionItem::query()->create([
                'transaction_id' => $transaction->id,
                ...$preparedItem,
            ]);
        }

        return $transaction->refresh();
    }

    private function storedMoney(Product $product, string $field): string
    {
        $value = $product->getRawOriginal($field);

        if (! is_string($value)) {
            throw new LogicException(
                "Demo product [{$product->sku}] {$field} must be a decimal string."
            );
        }

        return $value;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function at(
        CarbonImmutable $timestamp,
        Closure $callback
    ): mixed {
        $previousTestNow = CarbonImmutable::getTestNow();
        CarbonImmutable::setTestNow($timestamp);

        try {
            return $callback();
        } finally {
            CarbonImmutable::setTestNow($previousTestNow);
        }
    }
}
