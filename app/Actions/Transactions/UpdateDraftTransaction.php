<?php

namespace App\Actions\Transactions;

use App\Actions\Transactions\Support\TransactionDraftBuilder;
use App\Actions\Transactions\Support\TransactionDraftValidator;
use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidTransactionDraft;
use App\Exceptions\Transactions\TransactionNotEditable;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;

final class UpdateDraftTransaction
{
    public function __construct(
        private readonly TransactionDraftValidator $validator,
        private readonly TransactionDraftBuilder $builder
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        Transaction $transaction,
        array $data
    ): Transaction {
        return DB::transaction(function () use (
            $transaction,
            $data
        ): Transaction {
            $lockedTransaction = Transaction::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedTransaction === null) {
                throw new InvalidTransactionDraft(
                    'Transaction does not exist.'
                );
            }

            $status = $lockedTransaction->getAttribute('status');

            if (! $status instanceof TransactionStatus) {
                throw new InvalidTransactionDraft(
                    'Transaction has an invalid status.'
                );
            }

            if ($status !== TransactionStatus::Draft) {
                throw new TransactionNotEditable(
                    "Transaction [{$lockedTransaction->transaction_number}] cannot be edited while its status is [{$status->value}]."
                );
            }

            $normalized = $this->validator->normalize($data);
            $draft = $this->builder->build($normalized);

            $lockedTransaction->fill([
                'customer_id' => $draft->customerId,
                'transaction_date' => $draft->transactionDate,
                ...$draft->totals->toArray(),
            ]);
            $lockedTransaction->save();

            TransactionItem::query()
                ->where('transaction_id', $lockedTransaction->id)
                ->delete();

            foreach ($draft->items as $item) {
                TransactionItem::query()->create([
                    'transaction_id' => $lockedTransaction->id,
                    ...$item,
                ]);
            }

            return $this->loadOrderedItems($lockedTransaction);
        });
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
