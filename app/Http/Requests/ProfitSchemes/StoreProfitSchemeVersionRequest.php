<?php

namespace App\Http\Requests\ProfitSchemes;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfitSchemeVersionRequest extends FormRequest
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
            'product_category_id' => ['required', 'integer', 'min:1'],
            'minimum_profit' => [
                'nullable',
                'string',
                'regex:/^-?\d{1,16}(?:\.\d{1,2})?$/D',
            ],
            'maximum_profit' => [
                'nullable',
                'string',
                'regex:/^-?\d{1,16}(?:\.\d{1,2})?$/D',
            ],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_until' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:effective_from',
            ],
            'priority' => ['required', 'integer'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{
     *     product_category_id: int,
     *     minimum_profit: string|null,
     *     maximum_profit: string|null,
     *     effective_from: string,
     *     effective_until: string|null,
     *     priority: int,
     *     is_active: bool
     * }
     */
    public function domainData(): array
    {
        $validated = $this->validated();

        return [
            'product_category_id' => (int) $validated[
                'product_category_id'
            ],
            'minimum_profit' => $validated['minimum_profit'] ?? null,
            'maximum_profit' => $validated['maximum_profit'] ?? null,
            'effective_from' => $validated['effective_from'],
            'effective_until' => $validated['effective_until'] ?? null,
            'priority' => (int) $validated['priority'],
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
