<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'email', 'phone', 'address'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeAvailableForTransaction(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('deleted_at'));
    }
}
