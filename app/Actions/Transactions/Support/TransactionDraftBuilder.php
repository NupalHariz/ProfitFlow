<?php

namespace App\Actions\Transactions\Support;

use App\Exceptions\Transactions\InvalidTransactionDraft;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProfitSharing\ProfitCalculator;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;

final class TransactionDraftBuilder
{
    public function __construct(
        private readonly ProfitCalculator $calculator
    ) {}

    /**
     * @param  array{
     *     customer_id: int,
     *     transaction_date: string,
     *     items: list<array{
     *         product_id: int,
     *         quantity: int,
     *         unit_selling_price: string|null
     *     }>
     * }  $data
     */
    public function build(array $data): PreparedTransactionDraft
    {
        $customer = Customer::query()
            ->availableForTransaction()
            ->select('id')
            ->whereKey($data['customer_id'])
            ->lockForUpdate()
            ->first();

        if ($customer === null) {
            throw new InvalidTransactionDraft(
                "Customer [{$data['customer_id']}] does not exist or has been deleted."
            );
        }

        $productIds = array_values(array_unique(array_map(
            static fn (array $item): int => $item['product_id'],
            $data['items']
        )));
        /** @var Collection<int|string, Product> $products */
        $products = Product::query()
            ->availableForTransaction()
            ->select([
                'id',
                'product_category_id',
                'name',
                'sku',
                'default_selling_price',
                'default_production_cost',
            ])
            ->with([
                'category:id,name',
            ])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
        $snapshots = [];
        $calculations = [];

        foreach ($data['items'] as $index => $item) {
            $product = $products->get($item['product_id']);

            if ($product === null) {
                throw new InvalidTransactionDraft(
                    "Transaction item [{$index}] product [{$item['product_id']}] does not exist, has been deleted, is inactive, or its category is unavailable for transaction drafts. Select an available replacement."
                );
            }

            $category = $product->category;

            if (! $category instanceof ProductCategory) {
                throw new InvalidTransactionDraft(
                    "Transaction item [{$index}] product [{$product->id}] category is unavailable for transaction drafts. Select an available replacement."
                );
            }

            $sellingPrice = $item['unit_selling_price']
                ?? $this->storedMoney(
                    $product,
                    'default_selling_price'
                );
            $productionCost = $this->storedMoney(
                $product,
                'default_production_cost'
            );

            try {
                $calculation = $this->calculator->calculateItem(
                    $item['quantity'],
                    $sellingPrice,
                    $productionCost
                );
            } catch (InvalidArgumentException $exception) {
                throw new InvalidTransactionDraft(
                    "Transaction item [{$index}] calculation failed: {$exception->getMessage()}",
                    previous: $exception
                );
            }

            $calculations[] = $calculation;
            $snapshots[] = [
                'product_id' => $product->id,
                'product_category_id' => $category->id,
                'applied_profit_scheme_version_id' => null,
                'product_name_snapshot' => $this->storedString(
                    $product,
                    'name'
                ),
                'product_sku_snapshot' => $this->storedString(
                    $product,
                    'sku'
                ),
                'category_name_snapshot' => $this->storedString(
                    $category,
                    'name'
                ),
                'quantity' => $item['quantity'],
                'unit_selling_price' => $sellingPrice,
                'unit_production_cost' => $productionCost,
                ...$calculation->toArray(),
                'rule_resolved_at' => null,
            ];
        }

        try {
            $totals = $this->calculator->aggregateItems($calculations);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidTransactionDraft(
                "Transaction total calculation failed: {$exception->getMessage()}",
                previous: $exception
            );
        }

        return new PreparedTransactionDraft(
            customerId: $data['customer_id'],
            transactionDate: $data['transaction_date'],
            items: $snapshots,
            totals: $totals,
        );
    }

    private function storedMoney(Product $product, string $field): string
    {
        $value = $product->getRawOriginal($field);

        if (! is_string($value)) {
            throw new LogicException(
                "Stored product {$field} must be a decimal string."
            );
        }

        return $value;
    }

    private function storedString(
        Product|ProductCategory $model,
        string $field
    ): string {
        $value = $model->getAttribute($field);

        if (! is_string($value)) {
            throw new LogicException(
                "Stored snapshot field {$field} must be a string."
            );
        }

        return $value;
    }
}
