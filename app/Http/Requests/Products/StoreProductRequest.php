<?php

namespace App\Http\Requests\Products;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProductRequest extends FormRequest
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
                Rule::unique('products', 'sku'),
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
            'is_active' => ['required', 'boolean'],
            ...$this->protectedFieldRules(),
        ];
    }

    /**
     * @return array{
     *     product_category_id: int,
     *     name: string,
     *     sku: string,
     *     description: string|null,
     *     default_selling_price: string,
     *     default_production_cost: string,
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
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'description' => $validated['description'] ?? null,
            'default_selling_price' => $validated[
                'default_selling_price'
            ],
            'default_production_cost' => $validated[
                'default_production_cost'
            ],
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
            'category',
            'transaction_items',
            'products_count',
            'category_is_active',
        ])->mapWithKeys(
            fn (string $field): array => [$field => ['prohibited']]
        )->all();
    }

    private function normalizeStrings(): void
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

    private function normalizedBoolean(mixed $value): mixed
    {
        return match (true) {
            $value === true, $value === 1, $value === '1' => true,
            $value === false, $value === 0, $value === '0' => false,
            default => $value,
        };
    }
}
