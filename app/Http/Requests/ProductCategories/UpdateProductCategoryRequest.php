<?php

namespace App\Http\Requests\ProductCategories;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProductCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'id' => ['prohibited'],
            'slug' => ['prohibited'],
            'is_active' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'products_count' => ['prohibited'],
            'profit_scheme_versions_count' => ['prohibited'],
        ];
    }

    /**
     * @return array{name: string, description: string|null}
     */
    public function domainData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('name'))) {
            $normalized['name'] = trim($this->input('name'));
        }

        if (is_string($this->input('description'))) {
            $description = trim($this->input('description'));
            $normalized['description'] = $description === ''
                ? null
                : $description;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
