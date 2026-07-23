<?php

namespace App\Actions\Transactions;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\ProfitDistributionRetryNotAllowed;
use App\Jobs\DistributeTransactionProfit;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class RetryProfitDistribution
{
    public function execute(Transaction $transaction): Transaction
    {
        $shouldDispatch = false;

        $result = DB::transaction(function () use (
            $transaction,
            &$shouldDispatch
        ): Transaction {
            if (! $transaction->exists
                || $transaction->getKey() === null) {
                throw new ProfitDistributionRetryNotAllowed(
                    'Transaction must be persisted before distribution can be retried.'
                );
            }

            $lockedTransaction = Transaction::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedTransaction === null) {
                throw new ProfitDistributionRetryNotAllowed(
                    'Transaction does not exist.'
                );
            }

            $status = $lockedTransaction->getAttribute('status');

            if (! $status instanceof TransactionStatus) {
                throw new ProfitDistributionRetryNotAllowed(
                    'Transaction has an invalid status.'
                );
            }

            if ($status === TransactionStatus::Distributed) {
                return $lockedTransaction->refresh();
            }

            if ($status === TransactionStatus::ProcessingDistribution) {
                throw new ProfitDistributionRetryNotAllowed(
                    "Transaction [{$lockedTransaction->transaction_number}] cannot be retried while distribution is processing."
                );
            }

            if (! in_array($status, [
                TransactionStatus::PendingDistribution,
                TransactionStatus::DistributionFailed,
            ], true)) {
                throw new ProfitDistributionRetryNotAllowed(
                    "Transaction [{$lockedTransaction->transaction_number}] cannot retry distribution while its status is [{$status->value}]."
                );
            }

            if ($lockedTransaction->distributions()->exists()) {
                throw new ProfitDistributionRetryNotAllowed(
                    "Transaction [{$lockedTransaction->transaction_number}] cannot retry distribution because immutable ledger rows already exist."
                );
            }

            if ($status === TransactionStatus::DistributionFailed) {
                $lockedTransaction->fill([
                    'status' => TransactionStatus::PendingDistribution,
                    'failure_reason' => null,
                    'distribution_started_at' => null,
                    'distributed_at' => null,
                ]);
                $lockedTransaction->save();
            }

            $shouldDispatch = true;

            return $lockedTransaction->refresh();
        });

        if ($shouldDispatch) {
            DistributeTransactionProfit::dispatch((int) $result->getKey())
                ->onQueue('critical')
                ->afterCommit();
        }

        return $result;
    }
}
