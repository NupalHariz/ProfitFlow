<?php

namespace App\Http\Requests\ProfitSchemes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfitSchemeVersionRequest extends FormRequest
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
            'product_category_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'minimum_profit' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^-?\d{1,16}(?:\.\d{1,2})?$/D',
            ],
            'maximum_profit' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^-?\d{1,16}(?:\.\d{1,2})?$/D',
            ],
            'effective_from' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
            ],
            'effective_until' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],
            'priority' => ['sometimes', 'required', 'integer'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    public function domainData(): array
    {
        $validated = $this->validated();
        $data = [];

        foreach ($validated as $field => $value) {
            $data[$field] = match ($field) {
                'product_category_id' => (int) $value,
                'priority' => (int) $value,
                'is_active' => (bool) $value,
                default => $value,
            };
        }

        return $data;
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
