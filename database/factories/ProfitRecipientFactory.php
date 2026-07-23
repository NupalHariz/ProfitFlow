<?php

namespace Database\Factories;

use App\Enums\ProfitRecipientType;
use App\Models\Investor;
use App\Models\ProfitRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfitRecipient>
 */
class ProfitRecipientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('RECIPIENT-####-????')),
            'type' => ProfitRecipientType::Investor,
            'investor_id' => Investor::factory(),
            'display_name' => fn (array $attributes): string => Investor::query()
                ->whereKey($attributes['investor_id'])
                ->firstOrFail()
                ->name,
            'is_active' => true,
        ];
    }

    public function company(): static
    {
        return $this->state(fn (): array => [
            'type' => ProfitRecipientType::Company,
            'investor_id' => null,
            'display_name' => $this->faker->company(),
        ]);
    }
}
