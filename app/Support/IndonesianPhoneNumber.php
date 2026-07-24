<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final class IndonesianPhoneNumber implements ValidationRule
{
    private const MAXIMUM_INPUT_LENGTH = 40;

    private const NATIONAL_NUMBER_PATTERN = '/^[1-9][0-9]{7,12}$/';

    private const PERMITTED_INPUT_PATTERN = '/^\+?[0-9\s().-]+$/';

    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        try {
            self::normalize($value);
        } catch (InvalidArgumentException) {
            $fail(
                'The :attribute must be a valid Indonesian phone number.'
            );
        }
    }

    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                'Phone number must be a string or null.'
            );
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > self::MAXIMUM_INPUT_LENGTH
            || preg_match(self::PERMITTED_INPUT_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(
                'Phone number contains an unsupported format.'
            );
        }

        $digits = preg_replace('/\D/', '', $value);

        if (! is_string($digits)) {
            throw new InvalidArgumentException(
                'Phone number could not be normalized.'
            );
        }

        $nationalNumber = match (true) {
            str_starts_with($value, '+62') => substr($digits, 2),
            str_starts_with($value, '+') => null,
            str_starts_with($digits, '62') => substr($digits, 2),
            str_starts_with($digits, '0') => substr($digits, 1),
            default => null,
        };

        if (! is_string($nationalNumber)
            || preg_match(
                self::NATIONAL_NUMBER_PATTERN,
                $nationalNumber
            ) !== 1) {
            throw new InvalidArgumentException(
                'Phone number must use an Indonesian 0 or +62 prefix.'
            );
        }

        return '+62'.$nationalNumber;
    }

    public static function normalizeSearchPrefix(string $value): ?string
    {
        $value = trim($value);

        if ($value === ''
            || mb_strlen($value) > self::MAXIMUM_INPUT_LENGTH
            || preg_match(self::PERMITTED_INPUT_PATTERN, $value) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        return match (true) {
            str_starts_with($value, '+62') => '+'.$digits,
            str_starts_with($value, '+') => null,
            str_starts_with($digits, '62') => '+'.$digits,
            str_starts_with($digits, '0') => '+62'.substr($digits, 1),
            default => null,
        };
    }
}
