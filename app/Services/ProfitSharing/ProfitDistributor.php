<?php

namespace App\Services\ProfitSharing;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidProfitDistribution;
use App\Exceptions\Transactions\TransactionDistributionNotProcessable;
use App\Models\ProfitDistribution;
use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ProfitDistributor
{
    public function __construct(
        private readonly ProfitAllocator $allocator
    ) {}

    public function distribute(int $transactionId): Transaction
    {
        $processing = $this->markProcessing($transactionId);

        if ($this->status($processing) === TransactionStatus::Distributed) {
            return $processing;
        }

        return $this->persistDistribution($transactionId);
    }

    private function markProcessing(int $transactionId): Transaction
    {
        return DB::transaction(function () use (
            $transactionId
        ): Transaction {
            $transaction = Transaction::query()
                ->whereKey($transactionId)
                ->lockForUpdate()
                ->first();

            if ($transaction === null) {
                throw new InvalidProfitDistribution(
                    "Transaction [{$transactionId}] does not exist."
                );
            }

            $status = $this->status($transaction);

            if ($status === TransactionStatus::Distributed) {
                return $transaction->refresh();
            }

            if (! in_array($status, [
                TransactionStatus::PendingDistribution,
                TransactionStatus::ProcessingDistribution,
            ], true)) {
                throw new TransactionDistributionNotProcessable(
                    "Transaction [{$transaction->transaction_number}] cannot process distribution while its status is [{$status->value}]."
                );
            }

            $this->assertNoExistingDistributions($transaction);

            if ($status === TransactionStatus::PendingDistribution) {
                $transaction->fill([
                    'status' => TransactionStatus::ProcessingDistribution,
                    'distribution_started_at' => now(),
                    'failure_reason' => null,
                ]);
                $transaction->save();
            }

            return $transaction->refresh();
        });
    }

    private function persistDistribution(
        int $transactionId
    ): Transaction {
        return DB::transaction(function () use (
            $transactionId
        ): Transaction {
            $transaction = Transaction::query()
                ->whereKey($transactionId)
                ->lockForUpdate()
                ->first();

            if ($transaction === null) {
                throw new InvalidProfitDistribution(
                    "Transaction [{$transactionId}] does not exist."
                );
            }

            $status = $this->status($transaction);

            if ($status === TransactionStatus::Distributed) {
                return $transaction->refresh();
            }

            if ($status !== TransactionStatus::ProcessingDistribution) {
                throw new TransactionDistributionNotProcessable(
                    "Transaction [{$transaction->transaction_number}] cannot persist distribution while its status is [{$status->value}]."
                );
            }

            $this->assertNoExistingDistributions($transaction);

            /** @var Collection<int, TransactionItem> $items */
            $items = TransactionItem::query()
                ->select([
                    'id',
                    'transaction_id',
                    'applied_profit_scheme_version_id',
                    'net_profit',
                ])
                ->where('transaction_id', $transaction->id)
                ->orderBy('id')
                ->get();

            if ($items->isEmpty()) {
                throw new InvalidProfitDistribution(
                    "Transaction [{$transaction->transaction_number}] has no items to distribute."
                );
            }

            $positiveItems = $this->positiveItems($items);
            $versions = $this->loadFrozenVersions($positiveItems);
            $preparedRows = [];

            foreach ($positiveItems as $item) {
                $preparedRows = [
                    ...$preparedRows,
                    ...$this->prepareItemRows(
                        $transaction,
                        $item,
                        $versions
                    ),
                ];
            }

            $distributedAt = now();
            $rows = array_map(
                static fn (array $row): array => [
                    ...$row,
                    'created_at' => $distributedAt,
                ],
                $preparedRows
            );

            if ($rows !== []) {
                ProfitDistribution::query()->insert($rows);
            }

            $transaction->fill([
                'status' => TransactionStatus::Distributed,
                'distributed_at' => $distributedAt,
                'failure_reason' => null,
            ]);
            $transaction->save();

            return $transaction->refresh();
        });
    }

    private function status(Transaction $transaction): TransactionStatus
    {
        $status = $transaction->getAttribute('status');

        if (! $status instanceof TransactionStatus) {
            throw new InvalidProfitDistribution(
                'Transaction has an invalid distribution status.'
            );
        }

        return $status;
    }

    private function assertNoExistingDistributions(
        Transaction $transaction
    ): void {
        if ($transaction->distributions()->exists()) {
            throw new InvalidProfitDistribution(
                "Transaction [{$transaction->transaction_number}] has immutable distribution rows in a non-distributed state."
            );
        }
    }

    /**
     * @param  Collection<int, TransactionItem>  $items
     * @return Collection<int, TransactionItem>
     */
    private function positiveItems(Collection $items): Collection
    {
        return $items
            ->filter(function (TransactionItem $item): bool {
                $profit = $item->getRawOriginal('net_profit');

                if (! is_string($profit)) {
                    throw new InvalidProfitDistribution(
                        "Transaction item [{$item->id}] has an invalid frozen net profit."
                    );
                }

                try {
                    return FixedDecimal::moneyToMinorUnits($profit) > 0;
                } catch (InvalidArgumentException $exception) {
                    throw new InvalidProfitDistribution(
                        "Transaction item [{$item->id}] has an invalid frozen net profit: {$exception->getMessage()}",
                        previous: $exception
                    );
                }
            })
            ->values();
    }

    /**
     * @param  Collection<int, TransactionItem>  $positiveItems
     * @return Collection<int, ProfitSchemeVersion>
     */
    private function loadFrozenVersions(
        Collection $positiveItems
    ): Collection {
        $versionIds = [];

        foreach ($positiveItems as $item) {
            $versionId = $item->getRawOriginal(
                'applied_profit_scheme_version_id'
            );

            if (! is_int($versionId) || $versionId <= 0) {
                throw new InvalidProfitDistribution(
                    "Profitable transaction item [{$item->id}] is missing a valid frozen profit-scheme version."
                );
            }

            $versionIds[] = $versionId;
        }

        if ($versionIds === []) {
            return new Collection;
        }

        /** @var Collection<int, ProfitSchemeVersion> $versions */
        $versions = ProfitSchemeVersion::query()
            ->select(['id', 'published_at'])
            ->with([
                'allocations' => static function (
                    $query
                ): void {
                    $query
                        ->select([
                            'id',
                            'profit_scheme_version_id',
                            'profit_recipient_id',
                            'percentage',
                        ])
                        ->orderBy('id');
                },
                'allocations.recipient' => static function (
                    $query
                ): void {
                    $query->select(['id', 'display_name']);
                },
            ])
            ->whereKey(array_values(array_unique($versionIds)))
            ->get()
            ->keyBy('id');

        return $versions;
    }

    /**
     * @param  Collection<int, ProfitSchemeVersion>  $versions
     * @return list<array{
     *     transaction_id: int,
     *     transaction_item_id: int,
     *     profit_scheme_version_id: int,
     *     profit_recipient_id: int,
     *     recipient_name_snapshot: string,
     *     percentage: string,
     *     amount: string
     * }>
     */
    private function prepareItemRows(
        Transaction $transaction,
        TransactionItem $item,
        Collection $versions
    ): array {
        $versionId = $item->getRawOriginal(
            'applied_profit_scheme_version_id'
        );
        $profit = $item->getRawOriginal('net_profit');

        if (! is_int($versionId)
            || ! is_string($profit)) {
            throw new InvalidProfitDistribution(
                "Transaction item [{$item->id}] has invalid frozen distribution data."
            );
        }

        $version = $versions->get($versionId);

        if (! $version instanceof ProfitSchemeVersion) {
            throw new InvalidProfitDistribution(
                "Transaction item [{$item->id}] frozen profit-scheme version [{$versionId}] does not exist."
            );
        }

        if ($version->getRawOriginal('published_at') === null) {
            throw new InvalidProfitDistribution(
                "Transaction item [{$item->id}] frozen profit-scheme version [{$versionId}] is unpublished."
            );
        }

        /** @var Collection<int, ProfitSchemeAllocation> $allocations */
        $allocations = $version->getRelation('allocations');

        if ($allocations->isEmpty()) {
            throw new InvalidProfitDistribution(
                "Transaction item [{$item->id}] frozen profit-scheme version [{$versionId}] has no allocations."
            );
        }

        $entries = [];
        /** @var array<int, ProfitRecipient> $recipients */
        $recipients = [];

        foreach ($allocations as $allocation) {
            $recipientId = $allocation->getRawOriginal(
                'profit_recipient_id'
            );
            $percentage = $allocation->getRawOriginal('percentage');
            $recipient = $allocation->getRelation('recipient');

            if (! is_int($recipientId)
                || ! $recipient instanceof ProfitRecipient) {
                throw new InvalidProfitDistribution(
                    "Transaction item [{$item->id}] allocation [{$allocation->id}] has a missing recipient."
                );
            }

            if (! is_string($percentage)) {
                throw new InvalidProfitDistribution(
                    "Transaction item [{$item->id}] allocation [{$allocation->id}] has a malformed percentage."
                );
            }

            $entries[] = [
                'recipient_id' => $recipientId,
                'percentage' => $percentage,
            ];
            $recipients[$recipientId] = $recipient;
        }

        try {
            $results = $this->allocator->allocate(
                $profit,
                $entries
            );
        } catch (InvalidArgumentException $exception) {
            throw new InvalidProfitDistribution(
                "Transaction item [{$item->id}] allocation failed: {$exception->getMessage()}",
                previous: $exception
            );
        }

        $rows = [];
        $persistedAmount = 0;

        foreach ($results as $result) {
            if (! is_int($result->recipientId)
                || ! isset($recipients[$result->recipientId])) {
                throw new InvalidProfitDistribution(
                    "Transaction item [{$item->id}] allocation returned an invalid recipient."
                );
            }

            $amount = FixedDecimal::moneyToMinorUnits($result->amount);

            if ($amount < 0) {
                throw new InvalidProfitDistribution(
                    "Transaction item [{$item->id}] allocation returned a negative amount."
                );
            }

            if ($amount === 0) {
                continue;
            }

            $recipient = $recipients[$result->recipientId];
            $recipientName = $recipient->getAttribute('display_name');

            if (! is_string($recipientName)) {
                throw new InvalidProfitDistribution(
                    "Transaction item [{$item->id}] recipient [{$result->recipientId}] has an invalid display name."
                );
            }

            $persistedAmount = FixedDecimal::addMoney(
                $persistedAmount,
                $amount
            );
            $rows[] = [
                'transaction_id' => (int) $transaction->getKey(),
                'transaction_item_id' => (int) $item->getKey(),
                'profit_scheme_version_id' => $versionId,
                'profit_recipient_id' => $result->recipientId,
                'recipient_name_snapshot' => $recipientName,
                'percentage' => $result->percentage,
                'amount' => $result->amount,
            ];
        }

        if ($persistedAmount !== FixedDecimal::moneyToMinorUnits($profit)) {
            throw new InvalidProfitDistribution(
                "Transaction item [{$item->id}] persisted distribution amounts do not equal its positive net profit."
            );
        }

        return $rows;
    }
}
