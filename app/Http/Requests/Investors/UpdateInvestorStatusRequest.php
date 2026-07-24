<?php

namespace App\Http\Requests\Investors;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateInvestorStatusRequest extends FormRequest
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
            'name' => ['prohibited'],
            'email' => ['prohibited'],
            'phone' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'profit_recipient' => ['prohibited'],
            'profit_recipient_id' => ['prohibited'],
            'linked_recipient' => ['prohibited'],
            'allocations' => ['prohibited'],
            'distributions' => ['prohibited'],
            'allocation_summary' => ['prohibited'],
        ];
    }

    /**
     * @return array{is_active: bool}
     */
    public function domainData(): array
    {
        $validated = $this->validated();

        return [
            'is_active' => (bool) $validated['is_active'],
        ];
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
