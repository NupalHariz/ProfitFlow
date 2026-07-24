<?php

namespace App\Actions\Customers\Support;

use App\Exceptions\Customers\InvalidCustomer;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CustomerInputNormalizer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     name: string,
     *     email: string|null,
     *     phone: string|null,
     *     address: string|null
     * }
     */
    public function normalize(array $data): array
    {
        $this->assertOnlySupportedFields($data);

        if (! array_key_exists('name', $data)) {
            throw new InvalidCustomer(
                'Customer name is required and cannot exceed 160 characters.'
            );
        }

        return [
            'name' => $this->name($data['name']),
            'email' => $this->email($data['email'] ?? null),
            'phone' => $this->phone($data['phone'] ?? null),
            'address' => $this->nullableString(
                $data['address'] ?? null,
                'address'
            ),
        ];
    }

    private function name(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidCustomer(
                'Customer name is required and cannot exceed 160 characters.'
            );
        }

        $name = trim($value);

        if ($name === '' || Str::length($name) > 160) {
            throw new InvalidCustomer(
                'Customer name is required and cannot exceed 160 characters.'
            );
        }

        return $name;
    }

    private function email(mixed $value): ?string
    {
        $email = $this->nullableString($value, 'email', 255);

        if ($email !== null
            && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidCustomer(
                'Customer email must be a valid email address.'
            );
        }

        return $email;
    }

    private function phone(mixed $value): ?string
    {
        try {
            return IndonesianPhoneNumber::normalize($value);
        } catch (InvalidArgumentException) {
            throw new InvalidCustomer(
                'Customer phone must be a valid Indonesian phone number.'
            );
        }
    }

    private function nullableString(
        mixed $value,
        string $field,
        ?int $maximumLength = null
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidCustomer(
                "Customer {$field} must be a string or null."
            );
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        if ($maximumLength !== null
            && Str::length($normalized) > $maximumLength) {
            throw new InvalidCustomer(
                "Customer {$field} cannot exceed {$maximumLength} characters."
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertOnlySupportedFields(array $data): void
    {
        $unsupported = array_diff(array_keys($data), [
            'name',
            'email',
            'phone',
            'address',
        ]);

        if ($unsupported !== []) {
            throw new InvalidCustomer(
                'Unsupported customer fields: '
                .implode(', ', $unsupported).'.'
            );
        }
    }
}
