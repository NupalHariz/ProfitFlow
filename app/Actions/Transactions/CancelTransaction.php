<?php

namespace App\Actions\Transactions;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidTransactionCancellation;
use App\Exceptions\Transactions\TransactionNotCancellable;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CancelTransaction
{
    public function execute(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use (
            $transaction
        ): Transaction {
            if (! $transaction->exists
                || $transaction->getKey() === null) {
                throw new InvalidTransactionCancellation(
                    'Transaction must be persisted before it can be cancelled.'
                );
            }

            $lockedTransaction = Transaction::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedTransaction === null) {
                throw new InvalidTransactionCancellation(
                    'Transaction does not exist.'
                );
            }

            $status = $lockedTransaction->getAttribute('status');

            if (! $status instanceof TransactionStatus) {
                throw new InvalidTransactionCancellation(
                    'Transaction has an invalid status.'
                );
            }

            if ($status === TransactionStatus::Cancelled) {
                return $lockedTransaction->refresh();
            }

            if ($status !== TransactionStatus::Draft) {
                throw new TransactionNotCancellable(
                    "Transaction [{$lockedTransaction->transaction_number}] cannot be cancelled while its status is [{$status->value}]."
                );
            }

            $hasFrozenRule = $lockedTransaction->items()
                ->where(
                    static fn (Builder $query): Builder => $query
                        ->whereNotNull(
                            'applied_profit_scheme_version_id'
                        )
                        ->orWhereNotNull('rule_resolved_at')
                )
                ->exists();

            if ($hasFrozenRule) {
                throw new InvalidTransactionCancellation(
                    "Transaction [{$lockedTransaction->transaction_number}] cannot be cancelled because completed profit-sharing data is already recorded."
                );
            }

            if ($lockedTransaction->distributions()->exists()) {
                throw new InvalidTransactionCancellation(
                    "Transaction [{$lockedTransaction->transaction_number}] cannot be cancelled because profit distributions are already recorded."
                );
            }

            $lockedTransaction->fill([
                'status' => TransactionStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
            $lockedTransaction->save();

            return $lockedTransaction->refresh();
        });
    }
}
