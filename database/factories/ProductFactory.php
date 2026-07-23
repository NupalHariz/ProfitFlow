<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $productionCostInCents = $this->faker->numberBetween(1_000, 100_000);
        $sellingPriceInCents = $productionCostInCents
            + $this->faker->numberBetween(100, 50_000);

        return [
            'product_category_id' => ProductCategory::factory(),
            'name' => $this->faker->unique()->words(3, true),
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-####-????')),
            'description' => $this->faker->optional()->sentence(),
            'default_selling_price' => self::formatMinorUnits($sellingPriceInCents),
            'default_production_cost' => self::formatMinorUnits($productionCostInCents),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    private static function formatMinorUnits(int $minorUnits): string
    {
        return sprintf('%d.%02d', intdiv($minorUnits, 100), $minorUnits % 100);
    }
}
