<?php

namespace App\Actions\Investors\Support;

use App\Exceptions\Investors\InvalidInvestor;
use Illuminate\Support\Str;

final class InvestorInputNormalizer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     name: string,
     *     email: string|null,
     *     phone: string|null,
     *     is_active: bool
     * }
     */
    public function normalizeForCreation(array $data): array
    {
        $this->assertOnlySupportedFields($data, [
            'name',
            'email',
            'phone',
            'is_active',
        ]);
        $this->assertNameExists($data);

        if (! array_key_exists('is_active', $data)
            || ! is_bool($data['is_active'])) {
            throw new InvalidInvestor(
                'Investor is_active must be a boolean.'
            );
        }

        return [
            ...$this->normalizeEditableFields($data),
            'is_active' => $data['is_active'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     name: string,
     *     email: string|null,
     *     phone: string|null
     * }
     */
    public function normalizeForUpdate(array $data): array
    {
        $this->assertOnlySupportedFields($data, [
            'name',
            'email',
            'phone',
        ]);
        $this->assertNameExists($data);

        return $this->normalizeEditableFields($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     name: string,
     *     email: string|null,
     *     phone: string|null
     * }
     */
    private function normalizeEditableFields(array $data): array
    {
        return [
            'name' => $this->name($data['name']),
            'email' => $this->email($data['email'] ?? null),
            'phone' => $this->nullableString(
                $data['phone'] ?? null,
                'phone',
                40
            ),
        ];
    }

    private function name(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidInvestor(
                'Investor name is required and cannot exceed 160 characters.'
            );
        }

        $name = trim($value);

        if ($name === '' || Str::length($name) > 160) {
            throw new InvalidInvestor(
                'Investor name is required and cannot exceed 160 characters.'
            );
        }

        return $name;
    }

    private function email(mixed $value): ?string
    {
        $email = $this->nullableString($value, 'email', 255);

        if ($email !== null
            && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidInvestor(
                'Investor email must be a valid email address.'
            );
        }

        return $email;
    }

    private function nullableString(
        mixed $value,
        string $field,
        int $maximumLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidInvestor(
                "Investor {$field} must be a string or null."
            );
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        if (Str::length($normalized) > $maximumLength) {
            throw new InvalidInvestor(
                "Investor {$field} cannot exceed {$maximumLength} characters."
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertNameExists(array $data): void
    {
        if (! array_key_exists('name', $data)) {
            throw new InvalidInvestor(
                'Investor name is required and cannot exceed 160 characters.'
            );
        }
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
            throw new InvalidInvestor(
                'Unsupported investor fields: '
                .implode(', ', $unsupported).'.'
            );
        }
    }
}
