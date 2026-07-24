<?php

namespace App\Http\Requests\Investors;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInvestorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['required', 'boolean'],
            ...$this->protectedFieldRules(),
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     email: string|null,
     *     phone: string|null,
     *     is_active: bool
     * }
     */
    public function domainData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeStrings();

        if ($this->exists('is_active')) {
            $this->merge([
                'is_active' => $this->normalizedBoolean(
                    $this->input('is_active')
                ),
            ]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function protectedFieldRules(): array
    {
        return collect([
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            'profit_recipient',
            'profit_recipient_id',
            'linked_recipient',
            'allocations',
            'distributions',
            'allocation_summary',
        ])->mapWithKeys(
            fn (string $field): array => [$field => ['prohibited']]
        )->all();
    }

    private function normalizeStrings(): void
    {
        $normalized = [];

        foreach (['name', 'email', 'phone'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $normalized[$field] = $value === '' && $field !== 'name'
                    ? null
                    : $value;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    private function normalizedBoolean(mixed $value): mixed
    {
        return match (true) {
            $value === true, $value === 1, $value === '1' => true,
            $value === false, $value === 0, $value === '0' => false,
            default => $value,
        };
    }
}
