<?php

namespace App\Actions\ProfitRecipients\Support;

use App\Enums\ProfitRecipientType;
use App\Exceptions\ProfitRecipients\InvalidProfitRecipient;
use Illuminate\Support\Str;

final class ProfitRecipientInputNormalizer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     code: string,
     *     type: ProfitRecipientType,
     *     investor_id: int|null,
     *     display_name: string,
     *     is_active: bool
     * }
     */
    public function normalizeForCreation(array $data): array
    {
        $this->assertOnlySupportedFields($data, [
            'code',
            'type',
            'investor_id',
            'display_name',
            'is_active',
        ]);
        $this->assertRequiredFields($data, [
            'code',
            'type',
            'display_name',
            'is_active',
        ]);

        $type = $this->type($data['type']);
        $investorId = $data['investor_id'] ?? null;

        if ($type === ProfitRecipientType::Investor) {
            if (! is_int($investorId) || $investorId <= 0) {
                throw new InvalidProfitRecipient(
                    'An Investor recipient requires a valid Investor.'
                );
            }
        } elseif ($investorId !== null) {
            throw new InvalidProfitRecipient(
                'A Company recipient cannot be linked to an Investor.'
            );
        }

        if (! is_bool($data['is_active'])) {
            throw new InvalidProfitRecipient(
                'Profit Recipient is_active must be a boolean.'
            );
        }

        return [
            ...$this->normalizeEditableFields($data),
            'type' => $type,
            'investor_id' => $investorId,
            'is_active' => $data['is_active'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{code: string, display_name: string}
     */
    public function normalizeForUpdate(array $data): array
    {
        $this->assertOnlySupportedFields($data, [
            'code',
            'display_name',
        ]);
        $this->assertRequiredFields($data, [
            'code',
            'display_name',
        ]);

        return $this->normalizeEditableFields($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{is_active: bool}
     */
    public function normalizeForStatus(array $data): array
    {
        $this->assertOnlySupportedFields($data, ['is_active']);
        $this->assertRequiredFields($data, ['is_active']);

        if (! is_bool($data['is_active'])) {
            throw new InvalidProfitRecipient(
                'Profit Recipient is_active must be a boolean.'
            );
        }

        return ['is_active' => $data['is_active']];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{code: string, display_name: string}
     */
    private function normalizeEditableFields(array $data): array
    {
        return [
            'code' => $this->requiredString(
                $data['code'],
                'code',
                80
            ),
            'display_name' => $this->requiredString(
                $data['display_name'],
                'display name',
                160
            ),
        ];
    }

    private function type(mixed $value): ProfitRecipientType
    {
        if ($value instanceof ProfitRecipientType) {
            return $value;
        }

        if (is_string($value)) {
            $type = ProfitRecipientType::tryFrom($value);

            if ($type !== null) {
                return $type;
            }
        }

        throw new InvalidProfitRecipient(
            'Profit Recipient type must be investor or company.'
        );
    }

    private function requiredString(
        mixed $value,
        string $field,
        int $maximumLength
    ): string {
        if (! is_string($value)) {
            throw new InvalidProfitRecipient(
                "Profit Recipient {$field} is required and cannot exceed "
                ."{$maximumLength} characters."
            );
        }

        $normalized = trim($value);

        if ($normalized === ''
            || Str::length($normalized) > $maximumLength) {
            throw new InvalidProfitRecipient(
                "Profit Recipient {$field} is required and cannot exceed "
                ."{$maximumLength} characters."
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $supported
     */
    private function assertOnlySupportedFields(
        array $data,
        array $supported
    ): void {
        $unsupported = array_diff(array_keys($data), $supported);

        if ($unsupported !== []) {
            throw new InvalidProfitRecipient(
                'Unsupported Profit Recipient fields: '
                .implode(', ', $unsupported).'.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $required
     */
    private function assertRequiredFields(
        array $data,
        array $required
    ): void {
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidProfitRecipient(
                    "Profit Recipient field [{$field}] is required."
                );
            }
        }
    }
}
