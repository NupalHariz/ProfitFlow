<?php

namespace App\Http\Requests\ProfitSchemes;

use Illuminate\Foundation\Http\FormRequest;

class IndexProfitSchemeRequest extends FormRequest
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
            'status' => ['nullable', 'in:all,active,inactive'],
        ];
    }

    /**
     * @return array{search: string, status: 'all'|'active'|'inactive'}
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? 'all';

        return [
            'search' => $search,
            'status' => $status,
        ];
    }
}
