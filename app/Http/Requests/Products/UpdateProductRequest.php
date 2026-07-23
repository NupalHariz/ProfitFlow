<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'product_category_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('product_categories', 'id')
                    ->where(
                        fn (Builder $query): Builder => $query
                            ->whereNull('deleted_at')
                    ),
            ],
            'name' => ['required', 'string', 'max:160'],
            'sku' => [
                'required',
                'string',
                'max:80',
                Rule::unique('products', 'sku')
                    ->ignore($this->productId()),
            ],
            'description' => ['nullable', 'string'],
            'default_selling_price' => [
                'required',
                'string',
                'regex:/^\d{1,16}(?:\.\d{1,2})?$/D',
            ],
            'default_production_cost' => [
                'required',
                'string',
                'regex:/^\d{1,16}(?:\.\d{1,2})?$/D',
            ],
            'is_active' => ['prohibited'],
            'id' => ['prohibited'],
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
     * @return array{
     *     product_category_id: int,
     *     name: string,
     *     sku: string,
     *     description: string|null,
     *     default_selling_price: string,
     *     default_production_cost: string
     * }
     */
    public function domainData(): array
    {
        $validated = $this->validated();

        return [
            'product_category_id' => (int) $validated[
                'product_category_id'
            ],
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'description' => $validated['description'] ?? null,
            'default_selling_price' => $validated[
                'default_selling_price'
            ],
            'default_production_cost' => $validated[
                'default_production_cost'
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['name', 'sku'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim($this->input($field));
            }
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

    private function productId(): int
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? (int) $product->getKey()
            : 0;
    }
}
