<?php

namespace Database\Factories;

use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfitSchemeAllocation>
 */
class ProfitSchemeAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profit_scheme_version_id' => ProfitSchemeVersion::factory(),
            'profit_recipient_id' => ProfitRecipient::factory(),
            'percentage' => '100.0000',
        ];
    }
}
