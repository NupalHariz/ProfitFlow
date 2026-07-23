<?php

namespace App\Actions\ProfitSchemes\Support;

use App\Exceptions\ProfitSchemes\InvalidProfitSchemeVersion;
use App\Models\ProductCategory;
use App\Models\ProfitSchemeVersion;
use App\Services\ProfitSharing\FixedDecimal;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final class ProfitSchemeVersionValidator
{
    /**
     * @var list<string>
     */
    private const RULE_FIELDS = [
        'product_category_id',
        'minimum_profit',
        'maximum_profit',
        'effective_from',
        'effective_until',
        'priority',
        'is_active',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     product_category_id: int|null,
     *     minimum_profit: string|null,
     *     maximum_profit: string|null,
     *     effective_from: string,
     *     effective_until: string|null,
     *     priority: int,
     *     is_active: bool
     * }
     */
    public function normalizeForCreation(array $data): array
    {
        $this->assertOnlyRuleFields($data);
        $this->assertRequiredFields($data, [
            'effective_from',
            'priority',
            'is_active',
        ]);

        return $this->normalizeCandidate([
            'product_category_id' => $data['product_category_id'] ?? null,
            'minimum_profit' => $data['minimum_profit'] ?? null,
            'maximum_profit' => $data['maximum_profit'] ?? null,
            'effective_from' => $data['effective_from'],
            'effective_until' => $data['effective_until'] ?? null,
            'priority' => $data['priority'],
            'is_active' => $data['is_active'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|string|bool|null>
     */
    public function normalizeForUpdate(
        ProfitSchemeVersion $version,
        array $data
    ): array {
        if ($data === []) {
            throw new InvalidProfitSchemeVersion(
                'At least one draft rule field must be provided.'
            );
        }

        $this->assertOnlyRuleFields($data);

        $candidate = [
            'product_category_id' => $version->getAttribute(
                'product_category_id'
            ),
            'minimum_profit' => $version->getAttribute('minimum_profit'),
            'maximum_profit' => $version->getAttribute('maximum_profit'),
            'effective_from' => $version->getAttribute('effective_from'),
            'effective_until' => $version->getAttribute('effective_until'),
            'priority' => $version->getAttribute('priority'),
            'is_active' => $version->getAttribute('is_active'),
        ];

        foreach ($data as $field => $value) {
            $candidate[$field] = $value;
        }

        $normalizedCandidate = $this->normalizeCandidate($candidate);
        $normalizedUpdate = [];

        foreach (array_keys($data) as $field) {
            $normalizedUpdate[$field] = $normalizedCandidate[$field];
        }

        return $normalizedUpdate;
    }

    public function assertValidForPublishing(
        ProfitSchemeVersion $version
    ): void {
        $normalized = $this->normalizeCandidate([
            'product_category_id' => $version->getAttribute(
                'product_category_id'
            ),
            'minimum_profit' => $version->getAttribute('minimum_profit'),
            'maximum_profit' => $version->getAttribute('maximum_profit'),
            'effective_from' => $version->getAttribute('effective_from'),
            'effective_until' => $version->getAttribute('effective_until'),
            'priority' => $version->getAttribute('priority'),
            'is_active' => $version->getAttribute('is_active'),
        ]);

        if (! $normalized['is_active']) {
            throw new InvalidProfitSchemeVersion(
                'Inactive profit scheme versions cannot be published.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertOnlyRuleFields(array $data): void
    {
        $unsupportedFields = array_diff(
            array_keys($data),
            self::RULE_FIELDS
        );

        if ($unsupportedFields !== []) {
            throw new InvalidProfitSchemeVersion(
                'Unsupported or protected profit scheme version fields: '
                .implode(', ', $unsupportedFields).'.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     */
    private function assertRequiredFields(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidProfitSchemeVersion(
                    "Profit scheme version field [{$field}] is required."
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array{
     *     product_category_id: int|null,
     *     minimum_profit: string|null,
     *     maximum_profit: string|null,
     *     effective_from: string,
     *     effective_until: string|null,
     *     priority: int,
     *     is_active: bool
     * }
     */
    private function normalizeCandidate(array $candidate): array
    {
        $categoryId = $this->normalizeCategory(
            $candidate['product_category_id'] ?? null
        );
        $minimumProfit = $this->normalizeMoney(
            $candidate['minimum_profit'] ?? null,
            'minimum_profit'
        );
        $maximumProfit = $this->normalizeMoney(
            $candidate['maximum_profit'] ?? null,
            'maximum_profit'
        );
        $effectiveFrom = $this->normalizeDate(
            $candidate['effective_from'] ?? null,
            'effective_from'
        );
        $effectiveUntil = $this->normalizeNullableDate(
            $candidate['effective_until'] ?? null,
            'effective_until'
        );
        $priority = $candidate['priority'] ?? null;
        $isActive = $candidate['is_active'] ?? null;

        if (! is_int($priority)) {
            throw new InvalidProfitSchemeVersion(
                'Profit scheme version priority must be an integer.'
            );
        }

        if (! is_bool($isActive)) {
            throw new InvalidProfitSchemeVersion(
                'Profit scheme version is_active must be a boolean.'
            );
        }

        if ($minimumProfit !== null
            && $maximumProfit !== null
            && FixedDecimal::moneyToMinorUnits($minimumProfit)
                > FixedDecimal::moneyToMinorUnits($maximumProfit)) {
            throw new InvalidProfitSchemeVersion(
                'Minimum profit cannot exceed maximum profit.'
            );
        }

        if ($effectiveUntil !== null
            && $effectiveUntil < $effectiveFrom) {
            throw new InvalidProfitSchemeVersion(
                'Effective until cannot be before effective from.'
            );
        }

        return [
            'product_category_id' => $categoryId,
            'minimum_profit' => $minimumProfit,
            'maximum_profit' => $maximumProfit,
            'effective_from' => $effectiveFrom,
            'effective_until' => $effectiveUntil,
            'priority' => $priority,
            'is_active' => $isActive,
        ];
    }

    private function normalizeCategory(mixed $categoryId): ?int
    {
        if ($categoryId === null) {
            return null;
        }

        if (! is_int($categoryId)
            || $categoryId <= 0
            || ! ProductCategory::query()
                ->whereKey($categoryId)
                ->exists()) {
            throw new InvalidProfitSchemeVersion(
                'Product category must reference an existing category.'
            );
        }

        return $categoryId;
    }

    private function normalizeMoney(
        mixed $value,
        string $field
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidProfitSchemeVersion(
                "Profit scheme version {$field} must be a decimal string."
            );
        }

        try {
            return FixedDecimal::formatMoney(
                FixedDecimal::moneyToMinorUnits($value)
            );
        } catch (InvalidArgumentException $exception) {
            throw new InvalidProfitSchemeVersion(
                "Profit scheme version {$field} must be a valid decimal(18,2) value.",
                previous: $exception
            );
        }
    }

    private function normalizeNullableDate(
        mixed $value,
        string $field
    ): ?string {
        return $value === null
            ? null
            : $this->normalizeDate($value, $field);
    }

    private function normalizeDate(mixed $value, string $field): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if (! is_string($value)) {
            throw new InvalidProfitSchemeVersion(
                "Profit scheme version {$field} must be a valid date."
            );
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false
            || $date->format('Y-m-d') !== $value
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidProfitSchemeVersion(
                "Profit scheme version {$field} must be a valid YYYY-MM-DD date."
            );
        }

        return $date->format('Y-m-d');
    }
}
