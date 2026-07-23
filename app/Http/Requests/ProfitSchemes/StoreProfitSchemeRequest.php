<?php

namespace App\Http\Requests\ProfitSchemes;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfitSchemeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     code: string,
     *     description: string|null,
     *     is_active: bool
     * }
     */
    public function domainData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('is_active')) {
            $this->merge([
                'is_active' => $this->normalizedBoolean(
                    $this->input('is_active')
                ),
            ]);
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
