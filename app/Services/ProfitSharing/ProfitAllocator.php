<?php

namespace App\Services\ProfitSharing;

use InvalidArgumentException;

final class ProfitAllocator
{
    /**
     * Input order is preserved. The final entry receives the exact remainder.
     *
     * Each entry must have the shape:
     * array{recipient_id: int|string, percentage: string}.
     *
     * @param  array<array-key, mixed>  $entries
     * @return list<ProfitAllocationResult>
     */
    public function allocate(string $distributableProfit, array $entries): array
    {
        $profitInCents = FixedDecimal::moneyToMinorUnits($distributableProfit);

        if ($profitInCents <= 0) {
            return [];
        }

        if ($entries === []) {
            throw new InvalidArgumentException(
                'Positive profit requires at least one allocation entry.'
            );
        }

        if (! array_is_list($entries)) {
            throw new InvalidArgumentException(
                'Allocation entries must be provided as an explicitly ordered list.'
            );
        }

        $normalizedEntries = [];
        $seenRecipients = [];
        $percentageTotal = 0;

        foreach ($entries as $entry) {
            if (! is_array($entry)
                || ! array_key_exists('recipient_id', $entry)
                || ! array_key_exists('percentage', $entry)
                || (! is_int($entry['recipient_id'])
                    && ! is_string($entry['recipient_id']))
                || ! is_string($entry['percentage'])) {
                throw new InvalidArgumentException(
                    'Each allocation entry requires a scalar recipient_id and decimal percentage string.'
                );
            }

            $recipientId = $entry['recipient_id'];
            $percentageUnits = FixedDecimal::percentageToUnits(
                $entry['percentage']
            );

            if (is_string($recipientId) && $recipientId === '') {
                throw new InvalidArgumentException(
                    'Recipient identifiers cannot be empty.'
                );
            }

            $recipientKey = is_int($recipientId)
                ? "integer:{$recipientId}"
                : "string:{$recipientId}";

            if (isset($seenRecipients[$recipientKey])) {
                throw new InvalidArgumentException(
                    'Allocation recipient identifiers must be unique.'
                );
            }

            if ($percentageUnits <= 0) {
                throw new InvalidArgumentException(
                    'Allocation percentage must be greater than zero.'
                );
            }

            if ($percentageUnits > FixedDecimal::PERCENTAGE_TOTAL_UNITS) {
                throw new InvalidArgumentException(
                    'Allocation percentage cannot exceed 100.0000.'
                );
            }

            $seenRecipients[$recipientKey] = true;
            $percentageTotal += $percentageUnits;
            $normalizedEntries[] = [
                'recipient_id' => $recipientId,
                'percentage_units' => $percentageUnits,
            ];
        }

        if ($percentageTotal !== FixedDecimal::PERCENTAGE_TOTAL_UNITS) {
            throw new InvalidArgumentException(
                'Allocation percentages must total exactly 100.0000.'
            );
        }

        $results = [];
        $remainingInCents = $profitInCents;
        $lastIndex = count($normalizedEntries) - 1;

        foreach ($normalizedEntries as $index => $entry) {
            $amountInCents = $index === $lastIndex
                ? $remainingInCents
                : min(
                    FixedDecimal::roundedPercentageOfMoney(
                        $profitInCents,
                        $entry['percentage_units']
                    ),
                    $remainingInCents
                );

            $remainingInCents -= $amountInCents;
            $results[] = new ProfitAllocationResult(
                recipientId: $entry['recipient_id'],
                percentage: FixedDecimal::formatPercentage(
                    $entry['percentage_units']
                ),
                amount: FixedDecimal::formatMoney($amountInCents),
            );
        }

        $allocatedInCents = 0;

        foreach ($results as $result) {
            $allocatedInCents = FixedDecimal::addMoney(
                $allocatedInCents,
                FixedDecimal::moneyToMinorUnits($result->amount)
            );
        }

        if ($allocatedInCents !== $profitInCents) {
            throw new InvalidArgumentException(
                'Allocated amounts must equal distributable profit.'
            );
        }

        return $results;
    }
}
