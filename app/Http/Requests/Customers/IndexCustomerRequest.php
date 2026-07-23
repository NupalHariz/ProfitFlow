<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

final class IndexCustomerRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{search: string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
        ];
    }
}
