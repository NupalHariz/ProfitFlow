<?php

namespace App\Actions\Transactions;

use App\Actions\Transactions\Support\TransactionDraftBuilder;
use App\Actions\Transactions\Support\TransactionDraftValidator;
use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidTransactionDraft;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateTransaction
{
    public function __construct(
        private readonly TransactionDraftValidator $validator,
        private readonly TransactionDraftBuilder $builder
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $creator): Transaction
    {
        $normalized = $this->validator->normalize($data);

        if (! $creator->exists) {
            throw new InvalidTransactionDraft(
                'Transaction creator must be a persisted user.'
            );
        }

        return DB::transaction(function () use (
            $normalized,
            $creator
        ): Transaction {
            $draft = $this->builder->build($normalized);
            $transaction = Transaction::query()->create([
                'transaction_number' => 'TRX-'.Str::ulid(),
                'idempotency_key' => null,
                'customer_id' => $draft->customerId,
                'transaction_date' => $draft->transactionDate,
                'status' => TransactionStatus::Draft,
                ...$draft->totals->toArray(),
                'failure_reason' => null,
                'created_by_user_id' => $creator->id,
                'completed_by_user_id' => null,
                'completed_at' => null,
                'distribution_started_at' => null,
                'distributed_at' => null,
                'cancelled_at' => null,
            ]);

            foreach ($draft->items as $item) {
                TransactionItem::query()->create([
                    'transaction_id' => $transaction->id,
                    ...$item,
                ]);
            }

            return $this->loadOrderedItems($transaction);
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
