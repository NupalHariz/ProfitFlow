<?php

namespace App\Actions\ProfitSchemes\Support;

use App\Exceptions\ProfitSchemes\InvalidProfitSchemeAllocation;
use App\Models\ProfitRecipient;
use App\Services\ProfitSharing\FixedDecimal;
use InvalidArgumentException;

final class ProfitSchemeAllocationValidator
{
    /**
     * @param  array<array-key, mixed>  $entries
     * @return list<array{
     *     profit_recipient_id: int,
     *     percentage: string,
     *     percentage_units: int
     * }>
     */
    public function normalize(array $entries): array
    {
        if (! array_is_list($entries)) {
            throw new InvalidProfitSchemeAllocation(
                'Profit scheme allocations must be an explicitly ordered list.'
            );
        }

        $normalized = [];
        $seenRecipientIds = [];
        $recipientIndexes = [];

        foreach ($entries as $index => $entry) {
            if (! is_array($entry)
                || ! array_key_exists('profit_recipient_id', $entry)
                || ! array_key_exists('percentage', $entry)) {
                throw new InvalidProfitSchemeAllocation(
                    'Each allocation requires profit_recipient_id and percentage.'
                );
            }

            $recipientId = $entry['profit_recipient_id'];
            $percentage = $entry['percentage'];

            if (! is_int($recipientId) || $recipientId <= 0) {
                throw new InvalidProfitSchemeAllocation(
                    'Allocation profit_recipient_id must be a positive integer.',
                    allocationIndex: $index
                );
            }

            if (! is_string($percentage)) {
                throw new InvalidProfitSchemeAllocation(
                    'Allocation percentage must be a decimal string.'
                );
            }

            if (isset($seenRecipientIds[$recipientId])) {
                throw new InvalidProfitSchemeAllocation(
                    "Allocation recipient [{$recipientId}] is duplicated.",
                    allocationIndex: $index
                );
            }

            try {
                $percentageUnits = FixedDecimal::percentageToUnits(
                    $percentage
                );
            } catch (InvalidArgumentException $exception) {
                throw new InvalidProfitSchemeAllocation(
                    'Allocation percentage must be a valid decimal(7,4) value.',
                    previous: $exception
                );
            }

            if ($percentageUnits <= 0) {
                throw new InvalidProfitSchemeAllocation(
                    'Allocation percentage must be greater than zero.'
                );
            }

            if ($percentageUnits > FixedDecimal::PERCENTAGE_TOTAL_UNITS) {
                throw new InvalidProfitSchemeAllocation(
                    'Allocation percentage cannot exceed 100.0000.'
                );
            }

            $seenRecipientIds[$recipientId] = true;
            $recipientIndexes[$recipientId] = $index;
            $normalized[] = [
                'profit_recipient_id' => $recipientId,
                'percentage' => FixedDecimal::formatPercentage(
                    $percentageUnits
                ),
                'percentage_units' => $percentageUnits,
            ];
        }

        $this->assertRecipientsAreAvailable(
            array_keys($seenRecipientIds),
            $recipientIndexes
        );

        return $normalized;
    }

    /**
     * @param  list<array{
     *     profit_recipient_id: int,
     *     percentage: string,
     *     percentage_units: int
     * }>  $allocations
     */
    public function assertPublishable(array $allocations): void
    {
        if ($allocations === []) {
            throw new InvalidProfitSchemeAllocation(
                'A profit scheme version requires at least one allocation before publishing.'
            );
        }

        $totalUnits = 0;

        foreach ($allocations as $allocation) {
            $totalUnits += $allocation['percentage_units'];
        }

        if ($totalUnits !== FixedDecimal::PERCENTAGE_TOTAL_UNITS) {
            throw new InvalidProfitSchemeAllocation(
                'Allocation percentages must total exactly 100.0000; received '
                .FixedDecimal::formatPercentage($totalUnits).'.'
            );
        }
    }

    /**
     * @param  list<int>  $recipientIds
     * @param  array<int, int>  $recipientIndexes
     */
    private function assertRecipientsAreAvailable(
        array $recipientIds,
        array $recipientIndexes
    ): void {
        if ($recipientIds === []) {
            return;
        }

        $availableRecipientIds = ProfitRecipient::query()
            ->availableForAllocation()
            ->whereIn('id', $recipientIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $availableRecipientLookup = array_fill_keys(
            $availableRecipientIds,
            true
        );

        foreach ($recipientIds as $recipientId) {
            if (! isset($availableRecipientLookup[$recipientId])) {
                throw new InvalidProfitSchemeAllocation(
                    'The selected Profit Recipient is no longer available. '
                    .'Choose an active recipient and try again.',
                    allocationIndex: $recipientIndexes[$recipientId] ?? null
                );
            }
        }
    }
}
