<?php

namespace App\Http\Requests\ProfitRecipients;

use App\Models\ProfitRecipient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfitRecipientRequest extends FormRequest
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
        $recipient = $this->route('profitRecipient');

        return [
            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('profit_recipients', 'code')
                    ->ignore(
                        $recipient instanceof ProfitRecipient
                            ? $recipient->getKey()
                            : null
                    ),
            ],
            'display_name' => [
                'required',
                'string',
                'max:160',
            ],
            'type' => ['prohibited'],
            'investor_id' => ['prohibited'],
            'is_active' => ['prohibited'],
            ...$this->protectedFieldRules(),
        ];
    }

    /**
     * @return array{code: string, display_name: string}
     */
    public function domainData(): array
    {
        $validated = $this->validated();

        return [
            'code' => $validated['code'],
            'display_name' => $validated['display_name'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['code', 'display_name'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim($this->input($field));
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
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
            'investor',
            'allocations',
            'distributions',
            'allocation_count',
            'draft_allocation_count',
            'published_allocation_count',
            'distribution_count',
            'usage_summary',
        ])->mapWithKeys(
            fn (string $field): array => [$field => ['prohibited']]
        )->all();
    }
}
