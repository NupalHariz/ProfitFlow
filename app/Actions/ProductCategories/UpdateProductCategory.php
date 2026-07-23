<?php

namespace App\Actions\ProductCategories;

use App\Exceptions\ProductCategories\InvalidProductCategory;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UpdateProductCategory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        ProductCategory $category,
        array $data
    ): ProductCategory {
        $this->assertOnlySupportedFields($data);

        if (! array_key_exists('name', $data)) {
            throw new InvalidProductCategory(
                'Product category field [name] is required.'
            );
        }

        $name = $this->normalizeName($data['name']);
        $description = $this->normalizeDescription(
            $data['description'] ?? null
        );

        return DB::transaction(function () use (
            $category,
            $name,
            $description
        ): ProductCategory {
            $lockedCategory = ProductCategory::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedCategory === null) {
                throw new InvalidProductCategory(
                    'Product category does not exist.'
                );
            }

            $lockedCategory->name = $name;
            $lockedCategory->description = $description;
            $lockedCategory->save();

            return $lockedCategory->refresh();
        });
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertOnlySupportedFields(array $data): void
    {
        $unsupported = array_diff(
            array_keys($data),
            ['name', 'description']
        );

        if ($unsupported !== []) {
            throw new InvalidProductCategory(
                'Unsupported product category fields: '
                .implode(', ', $unsupported).'.'
            );
        }
    }
}
