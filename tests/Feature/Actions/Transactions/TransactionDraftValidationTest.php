<?php

namespace Tests\Feature\Actions\Transactions;

use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidTransactionDraft;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class TransactionDraftValidationTest extends TransactionActionTestCase
{
    public function test_empty_items_are_rejected(): void
    {
        $this->assertInvalidPayload(
            $this->validPayload(items: []),
            'non-empty'
        );
    }

    public function test_nonexistent_customer_is_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['customer_id'] = 2_147_483_647;

        $this->assertInvalidPayload($payload, 'Customer');
    }

    public function test_soft_deleted_customer_is_rejected(): void
    {
        $customerId = $this->customer->id;
        $this->customer->delete();
        $payload = $this->validPayload();
        $payload['customer_id'] = $customerId;

        $this->assertInvalidPayload($payload, 'deleted');
    }

    public function test_create_locks_available_customer_row(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->createDraft($this->validPayload());
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertTrue(collect($queries)->contains(
            fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "customers"'
            ) && str_contains(strtolower($query['query']), 'for update')
        ));
    }

    public function test_nonexistent_product_is_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['items'][0]['product_id'] = 2_147_483_647;

        $this->assertInvalidPayload($payload, 'product');
    }

    public function test_soft_deleted_product_is_rejected(): void
    {
        $product = $this->createProduct();
        $productId = $product->id;
        $product->delete();

        $this->assertInvalidPayload(
            $this->validPayload(items: [[
                'product_id' => $productId,
                'quantity' => 1,
            ]]),
            'deleted'
        );
    }

    public function test_inactive_product_is_rejected(): void
    {
        $product = $this->createProduct(attributes: [
            'is_active' => false,
        ]);

        $this->assertInvalidPayload(
            $this->validPayload($product),
            'inactive'
        );
    }

    public function test_product_under_inactive_category_is_rejected(): void
    {
        $category = $this->createCategory(['is_active' => false]);
        $product = $this->createProduct($category);

        $this->assertInvalidPayload(
            $this->validPayload($product),
            'category'
        );
    }

    public function test_product_under_soft_deleted_category_is_rejected(): void
    {
        $category = $this->createCategory();
        $product = $this->createProduct($category);
        $category->delete();

        $this->assertInvalidPayload(
            $this->validPayload($product),
            'category'
        );
    }

    #[DataProvider('invalidQuantities')]
    public function test_non_positive_or_unsupported_quantities_are_rejected(
        mixed $quantity
    ): void {
        $payload = $this->validPayload();
        $payload['items'][0]['quantity'] = $quantity;

        $this->assertInvalidPayload($payload, 'quantity');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidQuantities(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'numeric string' => ['2'],
            'beyond PostgreSQL integer' => [2_147_483_648],
        ];
    }

    public function test_negative_selling_price_is_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['items'][0]['unit_selling_price'] = '-0.01';

        $this->assertInvalidPayload($payload, 'negative');
    }

    #[DataProvider('malformedSellingPrices')]
    public function test_malformed_selling_prices_are_rejected(
        mixed $price
    ): void {
        $payload = $this->validPayload();
        $payload['items'][0]['unit_selling_price'] = $price;

        $this->assertInvalidPayload(
            $payload,
            'unit_selling_price'
        );
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformedSellingPrices(): array
    {
        return [
            'alphabetic' => ['money'],
            'leading whitespace' => [' 100.00'],
            'trailing whitespace' => ['100.00 '],
            'comma separated' => ['1,000.00'],
            'scientific notation' => ['1e2'],
            'boolean' => [true],
            'integer rather than string' => [100],
        ];
    }

    public function test_excess_selling_price_precision_is_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['items'][0]['unit_selling_price'] = '10.001';

        $this->assertInvalidPayload(
            $payload,
            'unit_selling_price'
        );
    }

    public function test_overflow_selling_price_is_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['items'][0]['unit_selling_price'] = '10000000000000000.00';

        $this->assertInvalidPayload(
            $payload,
            'unit_selling_price'
        );
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_transaction_dates_are_rejected(
        mixed $date
    ): void {
        $payload = $this->validPayload();
        $payload['transaction_date'] = $date;

        $this->assertInvalidPayload($payload, 'date');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidDates(): array
    {
        return [
            'impossible date' => ['2026-02-30'],
            'date and time' => ['2026-07-23 12:00:00'],
            'alternate format' => ['23-07-2026'],
            'whitespace' => [' 2026-07-23'],
            'non-string' => [20260723],
        ];
    }

    #[DataProvider('protectedTransactionFields')]
    public function test_protected_transaction_fields_are_explicitly_rejected(
        string $field,
        mixed $value
    ): void {
        $payload = $this->validPayload();
        $payload[$field] = $value;

        $this->assertInvalidPayload($payload, $field);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function protectedTransactionFields(): array
    {
        return [
            'transaction number' => ['transaction_number', 'TRX-CLIENT'],
            'status' => ['status', TransactionStatus::Distributed->value],
            'total revenue' => ['total_revenue', '1.00'],
            'total cost' => ['total_production_cost', '1.00'],
            'net profit' => ['total_net_profit', '0.00'],
            'creator' => ['created_by_user_id', 999],
            'completer' => ['completed_by_user_id', 999],
            'completed at' => ['completed_at', '2026-07-23 00:00:00'],
            'distribution started' => [
                'distribution_started_at',
                '2026-07-23 00:00:00',
            ],
            'distributed at' => [
                'distributed_at',
                '2026-07-23 00:00:00',
            ],
            'cancelled at' => ['cancelled_at', '2026-07-23 00:00:00'],
            'failure reason' => ['failure_reason', 'client value'],
            'idempotency key' => [
                'idempotency_key',
                '00000000-0000-0000-0000-000000000000',
            ],
        ];
    }

    #[DataProvider('protectedItemFields')]
    public function test_protected_item_snapshot_fields_are_explicitly_rejected(
        string $field
    ): void {
        $payload = $this->validPayload();
        $payload['items'][0][$field] = '1.00';

        $this->assertInvalidPayload($payload, $field);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function protectedItemFields(): array
    {
        return [
            'production cost' => ['unit_production_cost'],
            'product name' => ['product_name_snapshot'],
            'product sku' => ['product_sku_snapshot'],
            'category identity' => ['product_category_id'],
            'category name' => ['category_name_snapshot'],
            'total revenue' => ['total_revenue'],
            'total cost' => ['total_production_cost'],
            'net profit' => ['net_profit'],
            'applied rule' => ['applied_profit_scheme_version_id'],
            'rule timestamp' => ['rule_resolved_at'],
        ];
    }

    public function test_invalid_second_item_leaves_no_transaction_or_items(): void
    {
        $product = $this->createProduct();
        $payload = $this->validPayload(items: [
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => 2_147_483_647, 'quantity' => 1],
        ]);

        try {
            $this->createDraft($payload);
            $this->fail('Expected the second item to be rejected.');
        } catch (InvalidTransactionDraft) {
        }

        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, TransactionItem::query()->count());
    }

    public function test_required_header_and_item_fields_are_rejected_when_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['transaction_date']);
        $this->assertInvalidPayload($payload, 'transaction_date');

        $payload = $this->validPayload();
        unset($payload['items'][0]['product_id']);
        $this->assertInvalidPayload($payload, 'product_id');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertInvalidPayload(
        array $payload,
        string $messageFragment
    ): void {
        try {
            $this->createDraft($payload);
            $this->fail('Expected transaction payload to be rejected.');
        } catch (InvalidTransactionDraft $exception) {
            $this->assertStringContainsString(
                $messageFragment,
                $exception->getMessage()
            );
        }
    }
}
