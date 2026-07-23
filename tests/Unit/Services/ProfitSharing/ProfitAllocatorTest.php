<?php

namespace Tests\Unit\Services\ProfitSharing;

use App\Services\ProfitSharing\ProfitAllocationResult;
use App\Services\ProfitSharing\ProfitAllocator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProfitAllocatorTest extends TestCase
{
    private ProfitAllocator $allocator;

    protected function setUp(): void
    {
        $this->allocator = new ProfitAllocator;
    }

    public function test_it_allocates_a_simple_40_30_30_split(): void
    {
        $results = $this->allocator->allocate('100.00', [
            ['recipient_id' => 10, 'percentage' => '40.0000'],
            ['recipient_id' => 20, 'percentage' => '30.0000'],
            ['recipient_id' => 'company', 'percentage' => '30.0000'],
        ]);

        $this->assertSame([
            ['recipient_id' => 10, 'percentage' => '40.0000', 'amount' => '40.00'],
            ['recipient_id' => 20, 'percentage' => '30.0000', 'amount' => '30.00'],
            ['recipient_id' => 'company', 'percentage' => '30.0000', 'amount' => '30.00'],
        ], array_map(
            fn (ProfitAllocationResult $result): array => $result->toArray(),
            $results
        ));
    }

    public function test_difficult_rounding_assigns_exact_remainder_to_final_recipient(): void
    {
        $results = $this->allocator->allocate('100.00', [
            ['recipient_id' => 'A', 'percentage' => '33.3333'],
            ['recipient_id' => 'B', 'percentage' => '33.3333'],
            ['recipient_id' => 'C', 'percentage' => '33.3334'],
        ]);

        $this->assertSame(['A', 'B', 'C'], array_column(
            array_map(
                fn (ProfitAllocationResult $result): array => $result->toArray(),
                $results
            ),
            'recipient_id'
        ));
        $this->assertSame(['33.33', '33.33', '33.34'], array_column(
            array_map(
                fn (ProfitAllocationResult $result): array => $result->toArray(),
                $results
            ),
            'amount'
        ));
        $this->assertSame('33.34', $results[2]->amount);
        $this->assertSame(10_000, $this->sumAmountInCents($results));
    }

    public function test_one_cent_split_uses_input_order_deterministically(): void
    {
        $results = $this->allocator->allocate('0.01', [
            ['recipient_id' => 'A', 'percentage' => '50.0000'],
            ['recipient_id' => 'B', 'percentage' => '50.0000'],
        ]);

        $this->assertSame('0.01', $results[0]->amount);
        $this->assertSame('0.00', $results[1]->amount);
    }

    public function test_more_recipients_than_available_cents_never_produces_negative_amounts(): void
    {
        $results = $this->allocator->allocate('0.02', [
            ['recipient_id' => 'A', 'percentage' => '25.0000'],
            ['recipient_id' => 'B', 'percentage' => '25.0000'],
            ['recipient_id' => 'C', 'percentage' => '25.0000'],
            ['recipient_id' => 'D', 'percentage' => '25.0000'],
        ]);

        $this->assertSame(
            ['0.01', '0.01', '0.00', '0.00'],
            array_map(
                fn (ProfitAllocationResult $result): string => $result->amount,
                $results
            )
        );
        $this->assertSame(2, $this->sumAmountInCents($results));
    }

    public function test_large_profit_allocation_stays_exact_without_overflow(): void
    {
        $results = $this->allocator->allocate('9999999999999999.99', [
            ['recipient_id' => 'A', 'percentage' => '40.0000'],
            ['recipient_id' => 'B', 'percentage' => '30.0000'],
            ['recipient_id' => 'C', 'percentage' => '30.0000'],
        ]);

        $this->assertSame('4000000000000000.00', $results[0]->amount);
        $this->assertSame('3000000000000000.00', $results[1]->amount);
        $this->assertSame('2999999999999999.99', $results[2]->amount);
        $this->assertSame(
            999_999_999_999_999_999,
            $this->sumAmountInCents($results)
        );
    }

    public function test_small_percentage_allocation_is_exact(): void
    {
        $results = $this->allocator->allocate('100.00', [
            ['recipient_id' => 'A', 'percentage' => '0.0100'],
            ['recipient_id' => 'B', 'percentage' => '99.9900'],
        ]);

        $this->assertSame('0.01', $results[0]->amount);
        $this->assertSame('99.99', $results[1]->amount);
    }

    #[DataProvider('nonPositiveProfits')]
    public function test_non_positive_profit_returns_no_allocations(string $profit): void
    {
        $this->assertSame([], $this->allocator->allocate($profit, []));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonPositiveProfits(): array
    {
        return [
            'zero' => ['0.00'],
            'negative' => ['-25.50'],
        ];
    }

    public function test_empty_allocations_are_rejected_for_positive_profit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->allocator->allocate('1.00', []);
    }

    public function test_duplicate_recipient_identifiers_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->allocator->allocate('1.00', [
            ['recipient_id' => 'A', 'percentage' => '50.0000'],
            ['recipient_id' => 'A', 'percentage' => '50.0000'],
        ]);
    }

    #[DataProvider('invalidIndividualPercentages')]
    public function test_invalid_individual_percentage_is_rejected(string $percentage): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->allocator->allocate('1.00', [
            ['recipient_id' => 'A', 'percentage' => $percentage],
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIndividualPercentages(): array
    {
        return [
            'zero' => ['0.0000'],
            'negative' => ['-0.0001'],
            'above one hundred' => ['100.0001'],
            'unsupported precision' => ['100.00000'],
            'malformed' => ['one hundred'],
        ];
    }

    #[DataProvider('invalidPercentageTotals')]
    public function test_percentage_total_must_equal_exactly_one_hundred(
        string $first,
        string $second
    ): void {
        $this->expectException(InvalidArgumentException::class);

        $this->allocator->allocate('1.00', [
            ['recipient_id' => 'A', 'percentage' => $first],
            ['recipient_id' => 'B', 'percentage' => $second],
        ]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidPercentageTotals(): array
    {
        return [
            'below one hundred' => ['50.0000', '49.9999'],
            'above one hundred' => ['50.0001', '50.0000'],
        ];
    }

    #[DataProvider('malformedProfits')]
    public function test_malformed_profit_is_rejected(string $profit): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->allocator->allocate($profit, []);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedProfits(): array
    {
        return [
            'alphabetic' => ['abc'],
            'scientific notation' => ['1e2'],
            'unsupported precision' => ['1.001'],
            'whitespace' => [' 1.00'],
        ];
    }

    public function test_percentage_and_amount_outputs_are_normalized_strings(): void
    {
        $results = $this->allocator->allocate('10', [
            ['recipient_id' => 'A', 'percentage' => '40'],
            ['recipient_id' => 'B', 'percentage' => '30.0'],
            ['recipient_id' => 'C', 'percentage' => '30.000'],
        ]);

        foreach ($results as $result) {
            $this->assertMatchesRegularExpression('/^\d+\.\d{4}$/', $result->percentage);
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $result->amount);
            $this->assertIsString($result->percentage);
            $this->assertIsString($result->amount);
            $this->assertIsNotFloat($result->percentage);
            $this->assertIsNotFloat($result->amount);
        }
    }

    public function test_associative_entry_container_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->allocator->allocate('1.00', [
            'first' => ['recipient_id' => 'A', 'percentage' => '100.0000'],
        ]);
    }

    public function test_malformed_entry_shape_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->allocator->allocate('1.00', [
            ['recipient_id' => 'A'],
        ]);
    }

    /**
     * @param  list<ProfitAllocationResult>  $results
     */
    private function sumAmountInCents(array $results): int
    {
        $sum = 0;

        foreach ($results as $result) {
            $negative = str_starts_with($result->amount, '-');
            $unsigned = ltrim($result->amount, '-');
            [$whole, $fraction] = explode('.', $unsigned);
            $amount = ((int) $whole * 100) + (int) $fraction;
            $sum += $negative ? -$amount : $amount;
        }

        return $sum;
    }
}
