<?php

namespace App\Actions\Products\Support;

use App\Exceptions\Products\InvalidProduct;
use App\Services\ProfitSharing\FixedDecimal;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ProductInputNormalizer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     product_category_id: int,
     *     name: string,
     *     sku: string,
     *     description: string|null,
     *     default_selling_price: string,
     *     default_production_cost: string,
     *     is_active: bool
     * }
     */
    public function normalizeForCreation(array $data): array
    {
        $this->assertOnlySupportedFields($data, [
            'product_category_id',
            'name',
            'sku',
            'description',
            'default_selling_price',
            'default_production_cost',
            'is_active',
        ]);
        $this->assertRequiredFields($data, [
            'product_category_id',
            'name',
            'sku',
            'default_selling_price',
            'default_production_cost',
            'is_active',
        ]);

        $isActive = $data['is_active'];

        if (! is_bool($isActive)) {
            throw new InvalidProduct(
                'Product is_active must be a boolean.'
            );
        }

        return [
            ...$this->normalizeEditableFields($data),
            'is_active' => $isActive,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     product_category_id: int,
     *     name: string,
     *     sku: string,
     *     description: string|null,
     *     default_selling_price: string,
     *     default_production_cost: string
     * }
     */
    public function normalizeForUpdate(array $data): array
    {
        $this->assertOnlySupportedFields($data, [
            'product_category_id',
            'name',
            'sku',
            'description',
            'default_selling_price',
            'default_production_cost',
        ]);
        $this->assertRequiredFields($data, [
            'product_category_id',
            'name',
            'sku',
            'default_selling_price',
            'default_production_cost',
        ]);

        return $this->normalizeEditableFields($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     product_category_id: int,
     *     name: string,
     *     sku: string,
     *     description: string|null,
     *     default_selling_price: string,
     *     default_production_cost: string
     * }
     */
    private function normalizeEditableFields(array $data): array
    {
        $categoryId = $data['product_category_id'];

        if (! is_int($categoryId) || $categoryId <= 0) {
            throw new InvalidProduct(
                'Product category must be a positive integer identifier.'
            );
        }

        return [
            'product_category_id' => $categoryId,
            'name' => $this->normalizeName($data['name']),
            'sku' => $this->normalizeSku($data['sku']),
            'description' => $this->normalizeDescription(
                $data['description'] ?? null
            ),
            'default_selling_price' => $this->normalizeMoney(
                $data['default_selling_price'],
                'default selling price'
            ),
            'default_production_cost' => $this->normalizeMoney(
                $data['default_production_cost'],
                'default production cost'
            ),
        ];
    }

    private function normalizeName(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidProduct(
                'Product name is required and cannot exceed 160 characters.'
            );
        }

        $name = trim($value);

        if ($name === '' || Str::length($name) > 160) {
            throw new InvalidProduct(
                'Product name is required and cannot exceed 160 characters.'
            );
        }

        return $name;
    }

    private function normalizeSku(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidProduct(
                'Product SKU is required and cannot exceed 80 characters.'
            );
        }

        $sku = trim($value);

        if ($sku === '' || Str::length($sku) > 80) {
            throw new InvalidProduct(
                'Product SKU is required and cannot exceed 80 characters.'
            );
        }

        return $sku;
    }

    private function normalizeDescription(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidProduct(
                'Product description must be a string or null.'
            );
        }

        $description = trim($value);

        return $description === '' ? null : $description;
    }

    private function normalizeMoney(mixed $value, string $field): string
    {
        if (! is_string($value)) {
            throw new InvalidProduct(
                "Product {$field} must be a decimal string."
            );
        }

        try {
            $minorUnits = FixedDecimal::moneyToMinorUnits($value);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidProduct(
                "Product {$field} must be a valid decimal(18,2) string.",
                previous: $exception
            );
        }

        if ($minorUnits < 0) {
            throw new InvalidProduct(
                "Product {$field} cannot be negative."
            );
        }

        return FixedDecimal::formatMoney($minorUnits);
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
            throw new InvalidProduct(
                'Unsupported product fields: '
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
                throw new InvalidProduct(
                    "Product field [{$field}] is required."
                );
            }
        }
    }
}
