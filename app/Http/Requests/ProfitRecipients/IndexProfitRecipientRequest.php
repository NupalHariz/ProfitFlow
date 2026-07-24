<?php

namespace App\Http\Requests\ProfitRecipients;

use Illuminate\Foundation\Http\FormRequest;

final class IndexProfitRecipientRequest extends FormRequest
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
            'type' => ['nullable', 'in:all,investor,company'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{
     *     search: string,
     *     type: 'all'|'investor'|'company',
     *     status: 'all'|'active'|'inactive'
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'type' => $validated['type'] ?? 'all',
            'status' => $validated['status'] ?? 'all',
        ];
    }
}
