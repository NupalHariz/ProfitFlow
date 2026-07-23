<?php

namespace App\Actions\ProductCategories;

use App\Exceptions\ProductCategories\InvalidProductCategory;
use App\Models\ProductCategory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateProductCategory
{
    private const MAX_SLUG_ATTEMPTS = 100;

    /**
     * @param  array{
     *     name: mixed,
     *     description?: mixed,
     *     is_active: mixed
     * }  $data
     */
    public function execute(array $data): ProductCategory
    {
        $this->assertOnlySupportedFields($data);

        foreach (['name', 'is_active'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidProductCategory(
                    "Product category field [{$field}] is required."
                );
            }
        }

        $name = $this->normalizeName($data['name']);
        $description = $this->normalizeDescription(
            $data['description'] ?? null
        );
        $isActive = $data['is_active'];

        if (! is_bool($isActive)) {
            throw new InvalidProductCategory(
                'Product category is_active must be a boolean.'
            );
        }

        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'category';
        }

        for ($attempt = 1; $attempt <= self::MAX_SLUG_ATTEMPTS; $attempt++) {
            $slug = $this->slugCandidate($baseSlug, $attempt);

            try {
                return DB::transaction(
                    fn (): ProductCategory => ProductCategory::query()
                        ->create([
                            'name' => $name,
                            'slug' => $slug,
                            'description' => $description,
                            'is_active' => $isActive,
                        ])
                );
            } catch (QueryException $exception) {
                if (! $this->isSlugUniqueViolation($exception)) {
                    throw $exception;
                }
            }
        }

        throw new InvalidProductCategory(
            'Unable to generate a unique product category slug after '
            .self::MAX_SLUG_ATTEMPTS.' attempts.'
        );
    }

    private function normalizeName(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidProductCategory(
                'Product category name is required and cannot exceed 120 characters.'
            );
        }

        $name = trim($value);

        if ($name === '' || Str::length($name) > 120) {
            throw new InvalidProductCategory(
                'Product category name is required and cannot exceed 120 characters.'
            );
        }

        return $name;
    }

    private function normalizeDescription(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidProductCategory(
                'Product category description must be a string or null.'
            );
        }

        $description = trim($value);

        return $description === '' ? null : $description;
    }

    private function slugCandidate(string $baseSlug, int $attempt): string
    {
        $suffix = $attempt === 1 ? '' : '-'.$attempt;
        $maximumBaseLength = 140 - strlen($suffix);
        $candidateBase = rtrim(
            substr($baseSlug, 0, $maximumBaseLength),
            '-'
        );

        if ($candidateBase === '') {
            $candidateBase = 'category';
        }

        return $candidateBase.$suffix;
    }

    private function isSlugUniqueViolation(
        QueryException $exception
    ): bool {
        return (string) $exception->getCode() === '23505'
            && str_contains(
                $exception->getMessage(),
                'uq_product_categories_slug'
            );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertOnlySupportedFields(array $data): void
    {
        $unsupported = array_diff(array_keys($data), [
            'name',
            'description',
            'is_active',
        ]);

        if ($unsupported !== []) {
            throw new InvalidProductCategory(
                'Unsupported product category fields: '
                .implode(', ', $unsupported).'.'
            );
        }
    }
}
