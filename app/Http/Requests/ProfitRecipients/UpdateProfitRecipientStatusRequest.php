<?php

namespace App\Http\Requests\ProfitRecipients;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProfitRecipientStatusRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
            'id' => ['prohibited'],
            'code' => ['prohibited'],
            'type' => ['prohibited'],
            'investor_id' => ['prohibited'],
            'display_name' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'investor' => ['prohibited'],
            'allocations' => ['prohibited'],
            'distributions' => ['prohibited'],
            'allocation_count' => ['prohibited'],
            'draft_allocation_count' => ['prohibited'],
            'published_allocation_count' => ['prohibited'],
            'distribution_count' => ['prohibited'],
            'usage_summary' => ['prohibited'],
        ];
    }

    /**
     * @return array{is_active: bool}
     */
    public function domainData(): array
    {
        $validated = $this->validated();

        return ['is_active' => (bool) $validated['is_active']];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('is_active')) {
            $this->merge([
                'is_active' => $this->normalizedBoolean(
                    $this->input('is_active')
                ),
            ]);
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
