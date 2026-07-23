<?php

namespace App\Services\ProfitSharing;

use App\Models\ProfitSchemeVersion;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class ProfitRuleResolver
{
    public function resolve(
        CarbonInterface|string $transactionDate,
        ?int $productCategoryId,
        string $itemNetProfit,
    ): ?ProfitSchemeVersion {
        $date = $this->normalizeDate($transactionDate);
        $profit = FixedDecimal::formatMoney(
            FixedDecimal::moneyToMinorUnits($itemNetProfit)
        );

        return ProfitSchemeVersion::query()
            ->whereRelation('scheme', 'is_active', true)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('effective_from', '<=', $date)
            ->where(static function (Builder $query) use ($date): void {
                $query
                    ->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $date);
            })
            ->where(
                static function (Builder $query) use ($productCategoryId): void {
                    $query->whereNull('product_category_id');

                    if ($productCategoryId !== null) {
                        $query->orWhere(
                            'product_category_id',
                            $productCategoryId
                        );
                    }
                }
            )
            ->where(static function (Builder $query) use ($profit): void {
                $query
                    ->whereNull('minimum_profit')
                    ->orWhere('minimum_profit', '<=', $profit);
            })
            ->where(static function (Builder $query) use ($profit): void {
                $query
                    ->whereNull('maximum_profit')
                    ->orWhere('maximum_profit', '>=', $profit);
            })
            ->orderByDesc('priority')
            ->orderByDesc('published_at')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }

    private function normalizeDate(
        CarbonInterface|string $transactionDate
    ): string {
        if ($transactionDate instanceof CarbonInterface) {
            return $transactionDate->toDateString();
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $transactionDate
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || $date->format('Y-m-d') !== $transactionDate
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new InvalidArgumentException(
                'Transaction date must be a valid YYYY-MM-DD date.'
            );
        }

        return $date->format('Y-m-d');
    }
}
