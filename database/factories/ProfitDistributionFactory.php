<?php

namespace Database\Factories;

use App\Models\ProfitDistribution;
use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeVersion;
use App\Models\TransactionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfitDistribution>
 */
class ProfitDistributionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profit_scheme_version_id' => ProfitSchemeVersion::factory(),
            'transaction_item_id' => fn (array $attributes): TransactionItemFactory => TransactionItem::factory()
                ->withAppliedRule(
                    ProfitSchemeVersion::query()
                        ->whereKey($attributes['profit_scheme_version_id'])
                        ->firstOrFail()
                ),
            'transaction_id' => fn (array $attributes): int => TransactionItem::query()
                ->whereKey($attributes['transaction_item_id'])
                ->firstOrFail()
                ->transaction_id,
            'profit_recipient_id' => ProfitRecipient::factory(),
            'recipient_name_snapshot' => fn (array $attributes): string => ProfitRecipient::query()
                ->whereKey($attributes['profit_recipient_id'])
                ->firstOrFail()
                ->display_name,
            'percentage' => '100.0000',
            'amount' => '10.00',
        ];
    }
}
