<?php

namespace App\Actions\ProfitSchemes;

use App\Exceptions\ProfitSchemes\InvalidProfitScheme;
use App\Models\ProfitScheme;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class CreateProfitScheme
{
    /**
     * Codes are normalized to lowercase ASCII kebab-case.
     *
     * @param  array{
     *     name: mixed,
     *     code: mixed,
     *     description?: mixed,
     *     is_active: mixed
     * }  $data
     */
    public function execute(array $data, User $creator): ProfitScheme
    {
        $this->assertOnlySupportedFields($data);

        foreach (['name', 'code', 'is_active'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidProfitScheme(
                    "Profit scheme field [{$field}] is required."
                );
            }
        }

        $name = $data['name'];
        $code = $data['code'];
        $description = $data['description'] ?? null;
        $isActive = $data['is_active'];

        if (! is_string($name)
            || trim($name) === ''
            || Str::length(trim($name)) > 160) {
            throw new InvalidProfitScheme(
                'Profit scheme name is required and cannot exceed 160 characters.'
            );
        }

        if (! is_string($code)) {
            throw new InvalidProfitScheme(
                'Profit scheme code is required.'
            );
        }

        $normalizedCode = Str::slug($code);

        if ($normalizedCode === ''
            || Str::length($normalizedCode) > 80) {
            throw new InvalidProfitScheme(
                'Normalized profit scheme code is required and cannot exceed 80 characters.'
            );
        }

        if ($description !== null && ! is_string($description)) {
            throw new InvalidProfitScheme(
                'Profit scheme description must be a string or null.'
            );
        }

        if (! is_bool($isActive)) {
            throw new InvalidProfitScheme(
                'Profit scheme is_active must be a boolean.'
            );
        }

        if (! $creator->exists) {
            throw new InvalidProfitScheme(
                'Profit scheme creator must be a persisted user.'
            );
        }

        if (ProfitScheme::query()
            ->where('code', $normalizedCode)
            ->exists()) {
            throw new InvalidProfitScheme(
                "Profit scheme code [{$normalizedCode}] already exists."
            );
        }

        try {
            return ProfitScheme::query()->create([
                'name' => trim($name),
                'code' => $normalizedCode,
                'description' => $description,
                'is_active' => $isActive,
                'created_by_user_id' => $creator->id,
            ]);
        } catch (QueryException $exception) {
            if (str_contains(
                $exception->getMessage(),
                'uq_profit_schemes_code'
            )) {
                throw new InvalidProfitScheme(
                    "Profit scheme code [{$normalizedCode}] already exists.",
                    previous: $exception
                );
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertOnlySupportedFields(array $data): void
    {
        $unsupported = array_diff(array_keys($data), [
            'name',
            'code',
            'description',
            'is_active',
        ]);

        if ($unsupported !== []) {
            throw new InvalidProfitScheme(
                'Unsupported profit scheme fields: '
                .implode(', ', $unsupported).'.'
            );
        }
    }
}
