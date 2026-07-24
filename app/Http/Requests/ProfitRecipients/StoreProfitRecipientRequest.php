<?php

namespace App\Http\Requests\ProfitRecipients;

use App\Enums\ProfitRecipientType;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProfitRecipientRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('profit_recipients', 'code'),
            ],
            'type' => [
                'required',
                Rule::enum(ProfitRecipientType::class),
            ],
            'investor_id' => [
                'nullable',
                'required_if:type,investor',
                'prohibited_if:type,company',
                'integer',
                'min:1',
                Rule::exists('investors', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->whereNull('deleted_at')
                ),
            ],
            'display_name' => [
                'required',
                'string',
                'max:160',
            ],
            'is_active' => ['required', 'boolean'],
            ...$this->protectedFieldRules(),
        ];
    }

    /**
     * @return array{
     *     code: string,
     *     type: ProfitRecipientType,
     *     investor_id: int|null,
     *     display_name: string,
     *     is_active: bool
     * }
     */
    public function domainData(): array
    {
        $validated = $this->validated();

        return [
            'code' => $validated['code'],
            'type' => ProfitRecipientType::from($validated['type']),
            'investor_id' => isset($validated['investor_id'])
                ? (int) $validated['investor_id']
                : null,
            'display_name' => $validated['display_name'],
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['code', 'type', 'display_name'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim($this->input($field));
            }
        }

        if ($this->input('investor_id') === '') {
            $normalized['investor_id'] = null;
        }

        if ($this->exists('is_active')) {
            $normalized['is_active'] = $this->normalizedBoolean(
                $this->input('is_active')
            );
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

    private function normalizedBoolean(mixed $value): mixed
    {
        return match (true) {
            $value === true, $value === 1, $value === '1' => true,
            $value === false, $value === 0, $value === '0' => false,
            default => $value,
        };
    }
}
