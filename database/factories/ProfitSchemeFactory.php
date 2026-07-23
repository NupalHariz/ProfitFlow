<?php

namespace Database\Factories;

use App\Models\ProfitScheme;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfitScheme>
 */
class ProfitSchemeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company().' Profit Scheme',
            'code' => strtoupper($this->faker->unique()->bothify('SCHEME-####-????')),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
            'created_by_user_id' => User::factory(),
        ];
    }
}
