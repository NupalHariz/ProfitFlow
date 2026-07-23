<?php

namespace App\Models;

use App\Enums\ProfitRecipientType;
use Database\Factories\ProfitRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'type', 'investor_id', 'display_name', 'is_active'])]
class ProfitRecipient extends Model
{
    /** @use HasFactory<ProfitRecipientFactory> */
    use HasFactory;

    /** @return BelongsTo<Investor, $this> */
    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    /** @return HasMany<ProfitSchemeAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ProfitSchemeAllocation::class);
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
            'type' => ProfitRecipientType::class,
            'is_active' => 'boolean',
        ];
    }
}
