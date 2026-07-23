<?php

namespace App\Models;

use Database\Factories\ProfitSchemeAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['profit_scheme_version_id', 'profit_recipient_id', 'percentage'])]
class ProfitSchemeAllocation extends Model
{
    /** @use HasFactory<ProfitSchemeAllocationFactory> */
    use HasFactory;

    /** @return BelongsTo<ProfitSchemeVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProfitSchemeVersion::class, 'profit_scheme_version_id');
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
        ];
    }
}
