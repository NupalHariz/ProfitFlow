<?php

namespace App\Http\Requests\Investors;

use Illuminate\Foundation\Http\FormRequest;

final class IndexInvestorRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{
     *     search: string,
     *     status: 'all'|'active'|'inactive'
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $status = $validated['status'] ?? 'all';

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'status' => $status,
        ];
    }
}
