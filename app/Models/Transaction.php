<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'transaction_number',
    'idempotency_key',
    'customer_id',
    'transaction_date',
    'status',
    'total_revenue',
    'total_production_cost',
    'total_net_profit',
    'failure_reason',
    'created_by_user_id',
    'completed_by_user_id',
    'completed_at',
    'distribution_started_at',
    'distributed_at',
    'cancelled_at',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    /** @return HasMany<TransactionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    /** @return HasMany<ProfitDistribution, $this> */
    public function distributions(): HasMany
    {
        return $this->hasMany(ProfitDistribution::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'status' => TransactionStatus::class,
            'total_revenue' => 'decimal:2',
            'total_production_cost' => 'decimal:2',
            'total_net_profit' => 'decimal:2',
            'completed_at' => 'datetime',
            'distribution_started_at' => 'datetime',
            'distributed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
