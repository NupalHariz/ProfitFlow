<?php

namespace App\Http\Requests\Transactions;

use App\Enums\TransactionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexTransactionRequest extends FormRequest
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
            'status' => [
                'nullable',
                Rule::enum(TransactionStatus::class),
            ],
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'date_until' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
            'customer_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * @return array{
     *     search: string,
     *     status: string|null,
     *     date_from: string|null,
     *     date_until: string|null,
     *     customer_id: int|null
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'status' => isset($validated['status'])
                ? (string) $validated['status']
                : null,
            'date_from' => isset($validated['date_from'])
                ? (string) $validated['date_from']
                : null,
            'date_until' => isset($validated['date_until'])
                ? (string) $validated['date_until']
                : null,
            'customer_id' => isset($validated['customer_id'])
                ? (int) $validated['customer_id']
                : null,
        ];
    }
}
