<?php

namespace App\Http\Requests\Products;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexProductRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'category' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('product_categories', 'id')
                    ->where(
                        fn (Builder $query): Builder => $query
                            ->whereNull('deleted_at')
                    ),
            ],
            'status' => ['nullable', 'in:all,active,inactive'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{
     *     search: string,
     *     category: int|null,
     *     status: 'all'|'active'|'inactive'
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $category = $validated['category'] ?? null;
        $status = $validated['status'] ?? 'all';

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'category' => $category === null
                ? null
                : (int) $category,
            'status' => $status,
        ];
    }
}
