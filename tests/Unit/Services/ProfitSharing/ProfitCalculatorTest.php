<?php

namespace Tests\Unit\Services\ProfitSharing;

use App\Services\ProfitSharing\ItemProfitResult;
use App\Services\ProfitSharing\ProfitCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProfitCalculatorTest extends TestCase
{
    private ProfitCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ProfitCalculator;
    }

    public function test_it_calculates_basic_item_revenue_cost_and_profit(): void
    {
        $result = $this->calculator->calculateItem(1, '100.00', '60.00');

        $this->assertSame([
            'total_revenue' => '100.00',
            'total_production_cost' => '60.00',
            'net_profit' => '40.00',
        ], $result->toArray());
    }

    public function test_it_multiplies_quantity_and_decimal_prices_exactly(): void
    {
        $result = $this->calculator->calculateItem(3, '125000.50', '80000.25');

        $this->assertSame('375001.50', $result->totalRevenue);
        $this->assertSame('240000.75', $result->totalProductionCost);
        $this->assertSame('135000.75', $result->netProfit);
    }

    public function test_it_supports_large_decimal_18_2_values(): void
    {
        $result = $this->calculator->calculateItem(
            1,
            '9999999999999999.99',
            '1.00'
        );

        $this->assertSame('9999999999999999.99', $result->totalRevenue);
        $this->assertSame('1.00', $result->totalProductionCost);
        $this->assertSame('9999999999999998.99', $result->netProfit);
    }

    public function test_break_even_item_has_zero_profit(): void
    {
        $result = $this->calculator->calculateItem(5, '12.34', '12.34');

        $this->assertSame('61.70', $result->totalRevenue);
        $this->assertSame('61.70', $result->totalProductionCost);
        $this->assertSame('0.00', $result->netProfit);
    }

    public function test_loss_making_item_has_negative_profit(): void
    {
        $result = $this->calculator->calculateItem(2, '75.00', '100.00');

        $this->assertSame('150.00', $result->totalRevenue);
        $this->assertSame('200.00', $result->totalProductionCost);
        $this->assertSame('-50.00', $result->netProfit);
    }

    public function test_it_aggregates_multiple_item_results_exactly(): void
    {
        $totals = $this->calculator->aggregateItems([
            $this->calculator->calculateItem(2, '100.00', '40.00'),
            $this->calculator->calculateItem(3, '20.50', '10.25'),
        ]);

        $this->assertSame([
            'total_revenue' => '261.50',
            'total_production_cost' => '110.75',
            'total_net_profit' => '150.75',
        ], $totals->toArray());
    }

    public function test_aggregation_handles_profitable_break_even_and_loss_items(): void
    {
        $totals = $this->calculator->aggregateItems([
            $this->calculator->calculateItem(1, '100.00', '40.00'),
            $this->calculator->calculateItem(1, '25.00', '25.00'),
            $this->calculator->calculateItem(1, '10.00', '30.00'),
        ]);

        $this->assertSame('135.00', $totals->totalRevenue);
        $this->assertSame('95.00', $totals->totalProductionCost);
        $this->assertSame('40.00', $totals->totalNetProfit);
    }

    public function test_empty_aggregation_returns_normalized_zero_totals(): void
    {
        $totals = $this->calculator->aggregateItems([]);

        $this->assertSame('0.00', $totals->totalRevenue);
        $this->assertSame('0.00', $totals->totalProductionCost);
        $this->assertSame('0.00', $totals->totalNetProfit);
    }

    #[DataProvider('invalidQuantities')]
    public function test_non_positive_quantity_is_rejected(int $quantity): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculateItem($quantity, '10.00', '5.00');
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidQuantities(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    public function test_negative_selling_price_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculateItem(1, '-0.01', '0.00');
    }

    public function test_negative_production_cost_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculateItem(1, '0.00', '-0.01');
    }

    #[DataProvider('malformedMoneyValues')]
    public function test_malformed_or_unsupported_money_is_rejected(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculateItem(1, $value, '0.00');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedMoneyValues(): array
    {
        return [
            'alphabetic' => ['abc'],
            'scientific notation' => ['1e3'],
            'unsupported precision' => ['1.001'],
            'leading whitespace' => [' 1.00'],
            'trailing whitespace' => ['1.00 '],
            'comma separator' => ['1,00'],
            'missing integer' => ['.50'],
            'missing fraction' => ['1.'],
        ];
    }

    public function test_outputs_are_normalized_strings_and_never_floats(): void
    {
        $result = $this->calculator->calculateItem(2, '1', '0.5');

        $this->assertSame('2.00', $result->totalRevenue);
        $this->assertSame('1.00', $result->totalProductionCost);
        $this->assertSame('1.00', $result->netProfit);

        foreach ($result->toArray() as $value) {
            $this->assertIsString($value);
            $this->assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $value);
            $this->assertIsNotFloat($value);
        }
    }

    public function test_calculated_value_outside_decimal_18_2_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculateItem(2, '9999999999999999.99', '0.00');
    }

    public function test_aggregation_rejects_internally_inconsistent_results(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->aggregateItems([
            new ItemProfitResult('100.00', '40.00', '50.00'),
        ]);
    }
}
