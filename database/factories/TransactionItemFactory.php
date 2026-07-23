<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<TransactionItem>
 */
class TransactionItemFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (TransactionItem $item): void {
            $product = Product::query()
                ->with('category')
                ->whereKey($item->product_id)
                ->firstOrFail();

            $item->setAttribute('product_category_id', $product->product_category_id);
            $item->setAttribute('product_name_snapshot', $product->name);
            $item->setAttribute('product_sku_snapshot', $product->sku);
            $item->setAttribute('category_name_snapshot', $product->category->name);

            if ($item->getAttribute('unit_selling_price') === null) {
                $item->setAttribute(
                    'unit_selling_price',
                    $product->getRawOriginal('default_selling_price')
                );
            }

            if ($item->getAttribute('unit_production_cost') === null) {
                $item->setAttribute(
                    'unit_production_cost',
                    $product->getRawOriginal('default_production_cost')
                );
            }

            $attributes = $item->getAttributes();
            $quantity = $attributes['quantity'] ?? null;
            $unitSellingPrice = $attributes['unit_selling_price'] ?? null;
            $unitProductionCost = $attributes['unit_production_cost'] ?? null;

            if (! is_int($quantity)
                || ! is_string($unitSellingPrice)
                || ! is_string($unitProductionCost)) {
                throw new LogicException('Transaction item factory received invalid monetary inputs.');
            }

            $revenueInCents = self::toMinorUnits($unitSellingPrice) * $quantity;
            $costInCents = self::toMinorUnits($unitProductionCost) * $quantity;

            $item->setAttribute('total_revenue', self::formatMinorUnits($revenueInCents));
            $item->setAttribute('total_production_cost', self::formatMinorUnits($costInCents));
            $item->setAttribute('net_profit', self::formatMinorUnits($revenueInCents - $costInCents));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'product_id' => Product::factory(),
            'product_category_id' => null,
            'applied_profit_scheme_version_id' => null,
            'product_name_snapshot' => '',
            'product_sku_snapshot' => '',
            'category_name_snapshot' => null,
            'quantity' => 2,
            'unit_selling_price' => null,
            'unit_production_cost' => null,
            'total_revenue' => '0.00',
            'total_production_cost' => '0.00',
            'net_profit' => '0.00',
            'rule_resolved_at' => null,
        ];
    }

    public function profitable(): static
    {
        return $this->state(fn (): array => [
            'unit_selling_price' => '150.00',
            'unit_production_cost' => '100.00',
        ]);
    }

    public function breakEven(): static
    {
        return $this->state(fn (): array => [
            'unit_selling_price' => '100.00',
            'unit_production_cost' => '100.00',
        ]);
    }

    public function lossMaking(): static
    {
        return $this->state(fn (): array => [
            'unit_selling_price' => '75.00',
            'unit_production_cost' => '100.00',
        ]);
    }

    public function withAppliedRule(ProfitSchemeVersion $version): static
    {
        return $this->state(fn (): array => [
            'applied_profit_scheme_version_id' => $version->id,
            'rule_resolved_at' => now(),
        ]);
    }

    private static function toMinorUnits(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        $normalized = ltrim($amount, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $minorUnits = ((int) $whole * 100)
            + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$minorUnits : $minorUnits;
    }

    private static function formatMinorUnits(int $minorUnits): string
    {
        $sign = $minorUnits < 0 ? '-' : '';
        $absoluteMinorUnits = abs($minorUnits);

        return sprintf(
            '%s%d.%02d',
            $sign,
            intdiv($absoluteMinorUnits, 100),
            $absoluteMinorUnits % 100
        );
    }
}
