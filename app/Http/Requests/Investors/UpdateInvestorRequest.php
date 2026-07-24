<?php

namespace App\Http\Requests\Investors;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateInvestorRequest extends FormRequest
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
            'is_active' => ['prohibited'],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'profit_recipient' => ['prohibited'],
            'profit_recipient_id' => ['prohibited'],
            'linked_recipient' => ['prohibited'],
            'allocations' => ['prohibited'],
            'distributions' => ['prohibited'],
            'allocation_summary' => ['prohibited'],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     email: string|null,
     *     phone: string|null
     * }
     */
    public function domainData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ];
    }

    protected function prepareForValidation(): void
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
}
