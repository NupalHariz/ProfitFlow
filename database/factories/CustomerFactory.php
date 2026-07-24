<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->name(),
            'email' => $this->faker->optional()->safeEmail(),
            'phone' => $this->faker->optional()->numerify('+628##########'),
            'address' => $this->faker->optional()->address(),
        ];
    }
}
