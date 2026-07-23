<?php

namespace App\Models;

use Database\Factories\TransactionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'transaction_id',
    'product_id',
    'product_category_id',
    'applied_profit_scheme_version_id',
    'product_name_snapshot',
    'product_sku_snapshot',
    'category_name_snapshot',
    'quantity',
    'unit_selling_price',
    'unit_production_cost',
    'total_revenue',
    'total_production_cost',
    'net_profit',
    'rule_resolved_at',
])]
class TransactionItem extends Model
{
    /** @use HasFactory<TransactionItemFactory> */
    use HasFactory;

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** @return BelongsTo<ProfitSchemeVersion, $this> */
    public function appliedProfitSchemeVersion(): BelongsTo
    {
        return $this->belongsTo(
            ProfitSchemeVersion::class,
            'applied_profit_scheme_version_id'
        );
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
            'unit_selling_price' => 'decimal:2',
            'unit_production_cost' => 'decimal:2',
            'total_revenue' => 'decimal:2',
            'total_production_cost' => 'decimal:2',
            'net_profit' => 'decimal:2',
            'rule_resolved_at' => 'datetime',
        ];
    }
}
