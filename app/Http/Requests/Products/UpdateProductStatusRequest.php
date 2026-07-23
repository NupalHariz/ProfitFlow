<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProductStatusRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
            'id' => ['prohibited'],
            'product_category_id' => ['prohibited'],
            'name' => ['prohibited'],
            'sku' => ['prohibited'],
            'description' => ['prohibited'],
            'default_selling_price' => ['prohibited'],
            'default_production_cost' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'category' => ['prohibited'],
            'transaction_items' => ['prohibited'],
            'products_count' => ['prohibited'],
            'category_is_active' => ['prohibited'],
        ];
    }

    /**
     * @return array{is_active: bool}
     */
    public function domainData(): array
    {
        $validated = $this->validated();

        return [
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
