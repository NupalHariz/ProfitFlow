<?php

namespace Tests\Unit\Support;

use App\Support\IndonesianPhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IndonesianPhoneNumberTest extends TestCase
{
    #[DataProvider('validPhoneNumbers')]
    public function test_it_normalizes_supported_indonesian_formats(
        mixed $input,
        ?string $expected
    ): void {
        $this->assertSame(
            $expected,
            IndonesianPhoneNumber::normalize($input)
        );
    }

    /**
     * @return array<string, array{mixed, string|null}>
     */
    public static function validPhoneNumbers(): array
    {
        return [
            'null' => [null, null],
            'blank' => ['  ', null],
            'local mobile' => ['0812 3456-7890', '+6281234567890'],
            'country code mobile' => ['62 (812) 3456 7890', '+6281234567890'],
            'international mobile' => [
                '+62-812-3456-7890',
                '+6281234567890',
            ],
            'local landline' => ['(021) 555-0101', '+62215550101'],
            'international landline' => [
                '+62 274 555 0104',
                '+622745550104',
            ],
        ];
    }

    #[DataProvider('invalidPhoneNumbers')]
    public function test_it_rejects_unsupported_or_structurally_invalid_values(
        mixed $input
    ): void {
        $this->expectException(InvalidArgumentException::class);

        IndonesianPhoneNumber::normalize($input);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidPhoneNumbers(): array
    {
        return [
            'non-string' => [81234567890],
            'missing Indonesian prefix' => ['81234567890'],
            'unsupported country' => ['+1 555 010 1000'],
            'double-zero prefix' => ['0062 812 3456 7890'],
            'zero after country code' => ['+62 0812 3456 7890'],
            'letters' => ['0812-CALL-NOW'],
            'too short' => ['08123'],
            'too long' => ['+62'.str_repeat('1', 14)],
            'raw input too long' => [str_repeat('0', 41)],
        ];
    }

    #[DataProvider('phoneSearchPrefixes')]
    public function test_it_normalizes_equivalent_search_prefixes(
        string $input,
        ?string $expected
    ): void {
        $this->assertSame(
            $expected,
            IndonesianPhoneNumber::normalizeSearchPrefix($input)
        );
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function phoneSearchPrefixes(): array
    {
        return [
            'local' => ['0812', '+62812'],
            'country code' => ['62 812', '+62812'],
            'international' => ['+62-812', '+62812'],
            'name search' => ['Naufal', null],
            'unsupported country' => ['+1 555', null],
        ];
    }
}
