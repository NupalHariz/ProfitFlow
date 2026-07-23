<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeVersion;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfitSchemeVersion>
 */
class ProfitSchemeVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profit_scheme_id' => ProfitScheme::factory(),
            'version' => 1,
            'product_category_id' => null,
            'minimum_profit' => '0.01',
            'maximum_profit' => null,
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_until' => null,
            'priority' => 0,
            'is_active' => true,
            'published_at' => null,
            'created_by_user_id' => fn (array $attributes): int => ProfitScheme::query()
                ->whereKey($attributes['profit_scheme_id'])
                ->firstOrFail()
                ->created_by_user_id,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'published_at' => now(),
        ]);
    }

    public function general(): static
    {
        return $this->state(fn (): array => [
            'product_category_id' => null,
        ]);
    }

    public function forCategory(ProductCategory $category): static
    {
        return $this->state(fn (): array => [
            'product_category_id' => $category->id,
        ]);
    }

    public function effectiveOn(CarbonInterface|string $date): static
    {
        $effectiveDate = $date instanceof CarbonInterface
            ? $date
            : CarbonImmutable::parse($date);

        return $this->state(fn (): array => [
            'effective_from' => $effectiveDate->toDateString(),
            'effective_until' => null,
        ]);
    }
}
