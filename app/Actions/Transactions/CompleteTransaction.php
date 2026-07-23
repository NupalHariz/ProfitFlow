<?php

namespace App\Actions\Transactions;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidTransactionCompletion;
use App\Exceptions\Transactions\MissingTransactionItemCategory;
use App\Exceptions\Transactions\ProfitRuleNotFoundForTransactionItem;
use App\Exceptions\Transactions\TransactionNotCompletable;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\ProfitSharing\FixedDecimal;
use App\Services\ProfitSharing\ItemProfitResult;
use App\Services\ProfitSharing\ProfitCalculator;
use App\Services\ProfitSharing\ProfitRuleResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CompleteTransaction
{
    public function __construct(
        private readonly ProfitCalculator $calculator,
        private readonly ProfitRuleResolver $ruleResolver
    ) {}

    public function execute(
        Transaction $transaction,
        User $completer
    ): Transaction {
        return DB::transaction(function () use (
            $transaction,
            $completer
        ): Transaction {
            if (! $transaction->exists
                || $transaction->getKey() === null) {
                throw new InvalidTransactionCompletion(
                    'Transaction must be persisted before it can be completed.'
                );
            }

            $lockedTransaction = Transaction::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedTransaction === null) {
                throw new InvalidTransactionCompletion(
                    'Transaction does not exist.'
                );
            }

            $this->assertPersistedCompleter($completer);
            $this->assertDraft($lockedTransaction);

            /** @var Collection<int, TransactionItem> $items */
            $items = $lockedTransaction->items()
                ->orderBy('id')
                ->get();

            if ($items->isEmpty()) {
                throw new InvalidTransactionCompletion(
                    "Transaction [{$lockedTransaction->transaction_number}] cannot be completed without items."
                );
            }

            $transactionDate = $lockedTransaction->getRawOriginal(
                'transaction_date'
            );

            if (! is_string($transactionDate)) {
                throw new InvalidTransactionCompletion(
                    'Transaction has an invalid stored transaction date.'
                );
            }

            /** @var list<ItemProfitResult> $calculations */
            $calculations = [];
            /**
             * @var list<array{
             *     item: TransactionItem,
             *     calculation: ItemProfitResult,
             *     rule: ProfitSchemeVersion|null
             * }> $preparedItems
             */
            $preparedItems = [];

            foreach ($items as $item) {
                $calculation = $this->calculateItem($item);
                $calculations[] = $calculation;

                $preparedItems[] = [
                    'item' => $item,
                    'calculation' => $calculation,
                    'rule' => null,
                ];
            }

            try {
                $totals = $this->calculator->aggregateItems(
                    $calculations
                );
            } catch (InvalidArgumentException $exception) {
                throw new InvalidTransactionCompletion(
                    "Transaction [{$lockedTransaction->transaction_number}] total calculation failed because its stored financial snapshots are invalid: {$exception->getMessage()}",
                    previous: $exception
                );
            }

            foreach ($preparedItems as $index => $preparedItem) {
                $item = $preparedItem['item'];
                $calculation = $preparedItem['calculation'];

                if (FixedDecimal::moneyToMinorUnits(
                    $calculation->netProfit
                ) <= 0) {
                    continue;
                }

                $categoryId = $item->getRawOriginal(
                    'product_category_id'
                );

                if ($categoryId === null) {
                    throw new MissingTransactionItemCategory(
                        "Profitable transaction item [{$item->id}] is missing its product category identity."
                    );
                }

                if (! is_int($categoryId) || $categoryId <= 0) {
                    throw new InvalidTransactionCompletion(
                        "Transaction item [{$item->id}] has an invalid stored product category identity."
                    );
                }

                try {
                    $rule = $this->ruleResolver->resolve(
                        $transactionDate,
                        $categoryId,
                        $calculation->netProfit
                    );
                } catch (InvalidArgumentException $exception) {
                    throw new InvalidTransactionCompletion(
                        "Transaction item [{$item->id}] rule resolution failed because the stored snapshot is invalid: {$exception->getMessage()}",
                        previous: $exception
                    );
                }

                if ($rule === null) {
                    throw new ProfitRuleNotFoundForTransactionItem(
                        "No applicable profit-sharing rule was found for transaction item [{$item->id}] ({$item->product_sku_snapshot})."
                    );
                }

                $preparedItems[$index]['rule'] = $rule;
            }

            $completedAt = now();

            foreach ($preparedItems as $preparedItem) {
                $rule = $preparedItem['rule'];
                $preparedItem['item']->fill([
                    ...$preparedItem['calculation']->toArray(),
                    'applied_profit_scheme_version_id' => $rule?->id,
                    'rule_resolved_at' => $rule === null
                        ? null
                        : $completedAt,
                ]);
                $preparedItem['item']->save();
            }

            $lockedTransaction->fill([
                ...$totals->toArray(),
                'status' => TransactionStatus::PendingDistribution,
                'completed_by_user_id' => $completer->id,
                'completed_at' => $completedAt,
                'failure_reason' => null,
                'distribution_started_at' => null,
                'distributed_at' => null,
                'cancelled_at' => null,
            ]);
            $lockedTransaction->save();

            return $this->loadOrderedItems($lockedTransaction);
        });
    }

    private function assertPersistedCompleter(User $completer): void
    {
        if (! $completer->exists
            || $completer->getKey() === null
            || ! User::query()->whereKey($completer->getKey())->exists()) {
            throw new InvalidTransactionCompletion(
                'Transaction completer must be a persisted user.'
            );
        }
    }

    private function assertDraft(Transaction $transaction): void
    {
        $status = $transaction->getAttribute('status');

        if (! $status instanceof TransactionStatus) {
            throw new InvalidTransactionCompletion(
                'Transaction has an invalid status.'
            );
        }

        if ($status !== TransactionStatus::Draft) {
            throw new TransactionNotCompletable(
                "Transaction [{$transaction->transaction_number}] cannot be completed while its status is [{$status->value}]."
            );
        }
    }

    private function calculateItem(
        TransactionItem $item
    ): ItemProfitResult {
        $quantity = $item->getRawOriginal('quantity');
        $unitSellingPrice = $item->getRawOriginal(
            'unit_selling_price'
        );
        $unitProductionCost = $item->getRawOriginal(
            'unit_production_cost'
        );

        if (! is_int($quantity)
            || ! is_string($unitSellingPrice)
            || ! is_string($unitProductionCost)) {
            throw new InvalidTransactionCompletion(
                "Transaction item [{$item->id}] has an invalid stored financial snapshot."
            );
        }

        try {
            return $this->calculator->calculateItem(
                $quantity,
                $unitSellingPrice,
                $unitProductionCost
            );
        } catch (InvalidArgumentException $exception) {
            throw new InvalidTransactionCompletion(
                "Transaction item [{$item->id}] has an invalid stored financial snapshot: {$exception->getMessage()}",
                previous: $exception
            );
        }
    }

    private function loadOrderedItems(
        Transaction $transaction
    ): Transaction {
        $transaction->refresh();
        $transaction->setRelation(
            'items',
            $transaction->items()->orderBy('id')->get()
        );

        return $transaction;
    }
}
