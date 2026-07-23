<?php

namespace App\Actions\Transactions\Support;

use App\Exceptions\Transactions\InvalidTransactionDraft;
use App\Services\ProfitSharing\FixedDecimal;
use DateTimeImmutable;
use InvalidArgumentException;

final class TransactionDraftValidator
{
    /**
     * @param  array<string, mixed>  $data
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
    public function normalize(array $data): array
    {
        $this->assertOnlyFields(
            $data,
            ['customer_id', 'transaction_date', 'items'],
            'transaction draft'
        );
        $this->assertRequiredFields(
            $data,
            ['customer_id', 'transaction_date', 'items'],
            'Transaction draft'
        );

        $customerId = $data['customer_id'];

        if (! is_int($customerId) || $customerId <= 0) {
            throw new InvalidTransactionDraft(
                'Transaction customer_id must be a positive integer.'
            );
        }

        $transactionDate = $this->normalizeDate(
            $data['transaction_date']
        );
        $items = $data['items'];

        if (! is_array($items)
            || ! array_is_list($items)
            || $items === []) {
            throw new InvalidTransactionDraft(
                'Transaction items must be a non-empty ordered list.'
            );
        }

        $normalizedItems = [];

        foreach ($items as $index => $item) {
            $normalizedItems[] = $this->normalizeItem($item, $index);
        }

        return [
            'customer_id' => $customerId,
            'transaction_date' => $transactionDate,
            'items' => $normalizedItems,
        ];
    }

    /**
     * @return array{
     *     product_id: int,
     *     quantity: int,
     *     unit_selling_price: string|null
     * }
     */
    private function normalizeItem(mixed $item, int $index): array
    {
        if (! is_array($item)) {
            throw new InvalidTransactionDraft(
                "Transaction item [{$index}] must be an object-like array."
            );
        }

        $this->assertOnlyFields(
            $item,
            ['product_id', 'quantity', 'unit_selling_price'],
            "transaction item [{$index}]"
        );
        $this->assertRequiredFields(
            $item,
            ['product_id', 'quantity'],
            "Transaction item [{$index}]"
        );

        $productId = $item['product_id'];
        $quantity = $item['quantity'];

        if (! is_int($productId) || $productId <= 0) {
            throw new InvalidTransactionDraft(
                "Transaction item [{$index}] product_id must be a positive integer."
            );
        }

        if (! is_int($quantity)
            || $quantity <= 0
            || $quantity > 2_147_483_647) {
            throw new InvalidTransactionDraft(
                "Transaction item [{$index}] quantity must be a positive integer within the PostgreSQL integer range."
            );
        }

        return [
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_selling_price' => $this->normalizeSellingPrice(
                $item['unit_selling_price'] ?? null,
                $index
            ),
        ];
    }

    private function normalizeSellingPrice(
        mixed $value,
        int $index
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidTransactionDraft(
                "Transaction item [{$index}] unit_selling_price must be a fixed-decimal string or null."
            );
        }

        try {
            $minorUnits = FixedDecimal::moneyToMinorUnits($value);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidTransactionDraft(
                "Transaction item [{$index}] unit_selling_price must be a valid decimal(18,2) string.",
                previous: $exception
            );
        }

        if ($minorUnits < 0) {
            throw new InvalidTransactionDraft(
                "Transaction item [{$index}] unit_selling_price cannot be negative."
            );
        }

        return FixedDecimal::formatMoney($minorUnits);
    }

    private function normalizeDate(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidTransactionDraft(
                'Transaction date must be a valid YYYY-MM-DD date.'
            );
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false
            || ($errors !== false
                && ($errors['warning_count'] > 0
                    || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidTransactionDraft(
                'Transaction date must be a valid YYYY-MM-DD date.'
            );
        }

        return $date->format('Y-m-d');
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $supported
     */
    private function assertOnlyFields(
        array $data,
        array $supported,
        string $context
    ): void {
        $unsupported = array_diff(array_keys($data), $supported);

        if ($unsupported !== []) {
            throw new InvalidTransactionDraft(
                'Unsupported or protected '.$context.' fields: '
                .implode(', ', $unsupported).'.'
            );
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $required
     */
    private function assertRequiredFields(
        array $data,
        array $required,
        string $context
    ): void {
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidTransactionDraft(
                    "{$context} field [{$field}] is required."
                );
            }
        }
    }
}
