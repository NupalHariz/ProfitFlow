<?php

namespace App\Http\Requests\Customers;

use App\Support\IndonesianPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<IndonesianPhoneNumber|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:40',
                new IndonesianPhoneNumber,
            ],
            'address' => ['nullable', 'string'],
            'id' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'transactions' => ['prohibited'],
            'transactions_count' => ['prohibited'],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     email: string|null,
     *     phone: string|null,
     *     address: string|null
     * }
     */
    public function domainData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeStrings();
    }

    private function normalizeStrings(): void
    {
        $normalized = [];

        foreach (['name', 'email', 'phone', 'address'] as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            $normalized[$field] = $field !== 'name' && $value === ''
                ? null
                : $value;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
