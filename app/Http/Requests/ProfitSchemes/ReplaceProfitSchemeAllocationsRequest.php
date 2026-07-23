<?php

namespace App\Http\Requests\ProfitSchemes;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceProfitSchemeAllocationsRequest extends FormRequest
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
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*' => [
                'required',
                'array:profit_recipient_id,percentage',
            ],
            'allocations.*.profit_recipient_id' => [
                'required',
                'integer',
                'min:1',
                'distinct:strict',
            ],
            'allocations.*.percentage' => [
                'required',
                'string',
                'regex:/^\d{1,3}(?:\.\d{1,4})?$/D',
            ],
        ];
    }

    /**
     * @return list<array{
     *     profit_recipient_id: int,
     *     percentage: string
     * }>
     */
    public function allocations(): array
    {
        $validated = $this->validated();
        $allocations = [];

        foreach ($validated['allocations'] as $allocation) {
            $allocations[] = [
                'profit_recipient_id' => (int) $allocation[
                    'profit_recipient_id'
                ],
                'percentage' => $allocation['percentage'],
            ];
        }

        return $allocations;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'allocations.min' => 'Add at least one allocation.',
            'allocations.*.profit_recipient_id.distinct' => 'Each recipient may only be selected once.',
            'allocations.*.percentage.regex' => 'Percentages must use at most four decimal places.',
        ];
    }
}
