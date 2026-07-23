<?php

namespace App\Services\ProfitSharing;

use InvalidArgumentException;

/**
 * @internal
 */
final class FixedDecimal
{
    public const MAX_MONEY_MINOR_UNITS = 999_999_999_999_999_999;

    public const PERCENTAGE_TOTAL_UNITS = 1_000_000;

    public static function moneyToMinorUnits(string $value): int
    {
        return self::parse($value, scale: 2, maximumIntegerDigits: 16, label: 'Money');
    }

    public static function percentageToUnits(string $value): int
    {
        return self::parse($value, scale: 4, maximumIntegerDigits: 3, label: 'Percentage');
    }

    public static function formatMoney(int $minorUnits): string
    {
        return self::format($minorUnits, 2);
    }

    public static function formatPercentage(int $units): string
    {
        return self::format($units, 4);
    }

    public static function multiplyMoney(int $minorUnits, int $multiplier): int
    {
        if ($minorUnits < 0 || $multiplier < 0) {
            throw new InvalidArgumentException('Money multiplication requires non-negative values.');
        }

        if ($multiplier !== 0
            && $minorUnits > intdiv(self::MAX_MONEY_MINOR_UNITS, $multiplier)) {
            throw new InvalidArgumentException('Calculated money exceeds decimal(18, 2).');
        }

        return $minorUnits * $multiplier;
    }

    public static function addMoney(int $left, int $right): int
    {
        if ($right > 0 && $left > self::MAX_MONEY_MINOR_UNITS - $right) {
            throw new InvalidArgumentException('Calculated money exceeds decimal(18, 2).');
        }

        if ($right < 0 && $left < -self::MAX_MONEY_MINOR_UNITS - $right) {
            throw new InvalidArgumentException('Calculated money exceeds decimal(18, 2).');
        }

        return $left + $right;
    }

    public static function roundedPercentageOfMoney(
        int $moneyMinorUnits,
        int $percentageUnits
    ): int {
        if ($moneyMinorUnits < 0
            || $percentageUnits < 0
            || $percentageUnits > self::PERCENTAGE_TOTAL_UNITS) {
            throw new InvalidArgumentException('Percentage allocation requires valid non-negative values.');
        }

        $wholePercentageBlocks = intdiv(
            $moneyMinorUnits,
            self::PERCENTAGE_TOTAL_UNITS
        );
        $moneyRemainder = $moneyMinorUnits % self::PERCENTAGE_TOTAL_UNITS;
        $wholeAmount = $wholePercentageBlocks * $percentageUnits;
        $fractionNumerator = $moneyRemainder * $percentageUnits;
        $roundedFraction = intdiv(
            $fractionNumerator + intdiv(self::PERCENTAGE_TOTAL_UNITS, 2),
            self::PERCENTAGE_TOTAL_UNITS
        );

        return $wholeAmount + $roundedFraction;
    }

    private static function parse(
        string $value,
        int $scale,
        int $maximumIntegerDigits,
        string $label
    ): int {
        if (! preg_match('/^-?\d+(?:\.\d{1,'.$scale.'})?$/D', $value)) {
            throw new InvalidArgumentException(
                "{$label} must be a plain decimal string with at most {$scale} decimal places."
            );
        }

        $negative = str_starts_with($value, '-');
        $unsignedValue = $negative ? substr($value, 1) : $value;
        [$integerPart, $fractionPart] = array_pad(
            explode('.', $unsignedValue, 2),
            2,
            ''
        );
        $normalizedIntegerPart = ltrim($integerPart, '0');
        $normalizedIntegerPart = $normalizedIntegerPart === ''
            ? '0'
            : $normalizedIntegerPart;

        if (strlen($normalizedIntegerPart) > $maximumIntegerDigits) {
            throw new InvalidArgumentException(
                "{$label} exceeds its supported fixed-decimal range."
            );
        }

        $scaleFactor = $scale === 2 ? 100 : 10_000;
        $normalizedFraction = str_pad($fractionPart, $scale, '0');
        $units = ((int) $normalizedIntegerPart * $scaleFactor)
            + (int) $normalizedFraction;

        return $negative && $units !== 0 ? -$units : $units;
    }

    private static function format(int $units, int $scale): string
    {
        $negative = $units < 0;
        $absoluteUnits = abs($units);
        $scaleFactor = $scale === 2 ? 100 : 10_000;

        return sprintf(
            '%s%d.%0'.$scale.'d',
            $negative ? '-' : '',
            intdiv($absoluteUnits, $scaleFactor),
            $absoluteUnits % $scaleFactor
        );
    }
}
