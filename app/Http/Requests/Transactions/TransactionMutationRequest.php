<?php

namespace App\Http\Requests\Transactions;

use Illuminate\Foundation\Http\FormRequest;

abstract class TransactionMutationRequest extends FormRequest
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
            'customer_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'transaction_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
                'max:250',
            ],
            'items.*' => [
                'required',
                'array:product_id,quantity,unit_selling_price',
            ],
            'items.*.product_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:2147483647',
            ],
            'items.*.unit_selling_price' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^\d+(?:\.\d{1,2})?$/D',
            ],
            'transaction_number' => ['prohibited'],
            'status' => ['prohibited'],
            'total_revenue' => ['prohibited'],
            'total_production_cost' => ['prohibited'],
            'total_net_profit' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'completed_by_user_id' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'distribution_started_at' => ['prohibited'],
            'distributed_at' => ['prohibited'],
            'cancelled_at' => ['prohibited'],
            'failure_reason' => ['prohibited'],
            'idempotency_key' => ['prohibited'],
            'items.*.unit_production_cost' => ['prohibited'],
            'items.*.product_category_id' => ['prohibited'],
            'items.*.product_name_snapshot' => ['prohibited'],
            'items.*.product_sku_snapshot' => ['prohibited'],
            'items.*.category_name_snapshot' => ['prohibited'],
            'items.*.total_revenue' => ['prohibited'],
            'items.*.total_production_cost' => ['prohibited'],
            'items.*.net_profit' => ['prohibited'],
            'items.*.applied_profit_scheme_version_id' => [
                'prohibited',
            ],
            'items.*.rule_resolved_at' => ['prohibited'],
        ];
    }

    /**
     * @return array{
     *     customer_id: int,
     *     transaction_date: string,
     *     items: list<array{
     *         product_id: int,
     *         quantity: int,
     *         unit_selling_price: string|null
     *     }>
     * }
     */
    public function domainData(): array
    {
        $validated = $this->validated();
        $items = $validated['items'];

        return [
            'customer_id' => (int) $validated['customer_id'],
            'transaction_date' => (string) $validated[
                'transaction_date'
            ],
            'items' => array_values(array_map(
                static fn (array $item): array => [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'unit_selling_price' => isset(
                        $item['unit_selling_price']
                    )
                        ? (string) $item['unit_selling_price']
                        : null,
                ],
                $items
            )),
        ];
    }
}
