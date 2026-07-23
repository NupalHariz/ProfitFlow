<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaction_id',
    'transaction_item_id',
    'profit_scheme_version_id',
    'profit_recipient_id',
    'recipient_name_snapshot',
    'percentage',
    'amount',
])]
class ProfitDistribution extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<TransactionItem, $this> */
    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class);
    }

    /** @return BelongsTo<ProfitSchemeVersion, $this> */
    public function profitSchemeVersion(): BelongsTo
    {
        return $this->belongsTo(ProfitSchemeVersion::class);
    }

    /** @return BelongsTo<ProfitRecipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(ProfitRecipient::class, 'profit_recipient_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }
}
