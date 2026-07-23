<?php

namespace App\Models;

use Database\Factories\InvestorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'email', 'phone', 'is_active'])]
class Investor extends Model
{
    /** @use HasFactory<InvestorFactory> */
    use HasFactory, SoftDeletes;

    /** @return HasOne<ProfitRecipient, $this> */
    public function profitRecipient(): HasOne
    {
        return $this->hasOne(ProfitRecipient::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
