<?php

namespace App\Models;

use Database\Factories\ProfitSchemeVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'profit_scheme_id',
    'version',
    'product_category_id',
    'minimum_profit',
    'maximum_profit',
    'effective_from',
    'effective_until',
    'priority',
    'is_active',
    'published_at',
    'created_by_user_id',
])]
class ProfitSchemeVersion extends Model
{
    /** @use HasFactory<ProfitSchemeVersionFactory> */
    use HasFactory;

    /** @return BelongsTo<ProfitScheme, $this> */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(ProfitScheme::class, 'profit_scheme_id');
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** @return HasMany<ProfitSchemeAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ProfitSchemeAllocation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<TransactionItem, $this> */
    public function appliedTransactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class, 'applied_profit_scheme_version_id');
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
            'minimum_profit' => 'decimal:2',
            'maximum_profit' => 'decimal:2',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
