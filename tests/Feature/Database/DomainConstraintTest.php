<?php

namespace Tests\Feature\Database;

use App\Enums\ProfitRecipientType;
use App\Models\ProfitRecipient;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesDomainRecords;
use Tests\TestCase;

class DomainConstraintTest extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    public function test_duplicate_product_sku_is_rejected(): void
    {
        $product = $this->createProduct();

        $this->expectException(QueryException::class);

        $this->createProduct(attributes: ['sku' => $product->sku]);
    }

    public function test_duplicate_scheme_code_is_rejected(): void
    {
        $scheme = $this->createScheme();

        $this->expectException(QueryException::class);

        $this->createScheme(attributes: ['code' => $scheme->code]);
    }

    public function test_duplicate_scheme_version_is_rejected(): void
    {
        $creator = $this->createUser();
        $scheme = $this->createScheme($creator);
        $this->createSchemeVersion($scheme, $creator);

        $this->expectException(QueryException::class);

        $this->createSchemeVersion($scheme, $creator);
    }

    public function test_duplicate_recipient_in_one_scheme_version_is_rejected(): void
    {
        $version = $this->createSchemeVersion();
        $recipient = $this->createCompanyRecipient();
        $this->createAllocation($version, $recipient);

        $this->expectException(QueryException::class);

        $this->createAllocation($version, $recipient);
    }

    public function test_duplicate_investor_profit_recipient_is_rejected(): void
    {
        $investor = $this->createInvestor();
        $this->createInvestorRecipient($investor);

        $this->expectException(QueryException::class);

        $this->createInvestorRecipient($investor);
    }

    public function test_investor_recipient_requires_an_investor(): void
    {
        $this->expectCheckConstraintViolation(
            fn () => ProfitRecipient::query()->create([
                'code' => 'INVALID-INVESTOR',
                'type' => ProfitRecipientType::Investor,
                'investor_id' => null,
                'display_name' => 'Invalid investor',
            ])
        );
    }

    public function test_company_recipient_forbids_an_investor(): void
    {
        $investor = $this->createInvestor();

        $this->expectCheckConstraintViolation(
            fn () => ProfitRecipient::query()->create([
                'code' => 'INVALID-COMPANY',
                'type' => ProfitRecipientType::Company,
                'investor_id' => $investor->id,
                'display_name' => 'Invalid company',
            ])
        );
    }

    public function test_invalid_profit_recipient_type_is_rejected(): void
    {
        $this->expectCheckConstraintViolation(
            fn () => DB::table('profit_recipients')->insert([
                'code' => 'INVALID-TYPE',
                'type' => 'partner',
                'display_name' => 'Invalid type',
            ])
        );
    }

    public function test_invalid_transaction_status_is_rejected(): void
    {
        $transaction = $this->createTransaction();

        $this->expectCheckConstraintViolation(
            fn () => DB::table('transactions')
                ->where('id', $transaction->id)
                ->update(['status' => 'invalid'])
        );
    }

    #[DataProvider('invalidAllocationPercentages')]
    public function test_invalid_allocation_percentage_is_rejected(string $percentage): void
    {
        $version = $this->createSchemeVersion();
        $recipient = $this->createCompanyRecipient();

        $this->expectCheckConstraintViolation(
            fn () => $this->createAllocation($version, $recipient, [
                'percentage' => $percentage,
            ])
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAllocationPercentages(): array
    {
        return [
            'zero' => ['0.0000'],
            'negative' => ['-0.0001'],
            'above one hundred' => ['100.0001'],
        ];
    }

    public function test_invalid_effective_date_range_is_rejected(): void
    {
        $this->expectCheckConstraintViolation(
            fn () => $this->createSchemeVersion(attributes: [
                'effective_from' => '2026-07-10',
                'effective_until' => '2026-07-09',
            ])
        );
    }

    public function test_invalid_profit_range_is_rejected(): void
    {
        $this->expectCheckConstraintViolation(
            fn () => $this->createSchemeVersion(attributes: [
                'minimum_profit' => '100.00',
                'maximum_profit' => '99.99',
            ])
        );
    }

    public function test_non_positive_scheme_version_is_rejected(): void
    {
        $this->expectCheckConstraintViolation(
            fn () => $this->createSchemeVersion(attributes: ['version' => 0])
        );
    }

    #[DataProvider('negativeProductPriceFields')]
    public function test_negative_product_price_is_rejected(string $field): void
    {
        $this->expectCheckConstraintViolation(
            fn () => $this->createProduct(attributes: [$field => '-0.01'])
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function negativeProductPriceFields(): array
    {
        return [
            'selling price' => ['default_selling_price'],
            'production cost' => ['default_production_cost'],
        ];
    }

    #[DataProvider('negativeTransactionTotalFields')]
    public function test_negative_transaction_nonnegative_total_is_rejected(string $field): void
    {
        $this->expectCheckConstraintViolation(
            fn () => $this->createTransaction(attributes: [$field => '-0.01'])
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function negativeTransactionTotalFields(): array
    {
        return [
            'revenue' => ['total_revenue'],
            'production cost' => ['total_production_cost'],
        ];
    }

    #[DataProvider('invalidTransactionItemValues')]
    public function test_invalid_transaction_item_value_is_rejected(
        string $field,
        int|string $value
    ): void {
        $transaction = $this->createTransaction();

        $this->expectCheckConstraintViolation(
            fn () => $this->createTransactionItem(
                $transaction,
                attributes: [$field => $value]
            )
        );
    }

    /**
     * @return array<string, array{string, int|string}>
     */
    public static function invalidTransactionItemValues(): array
    {
        return [
            'zero quantity' => ['quantity', 0],
            'negative unit selling price' => ['unit_selling_price', '-0.01'],
            'negative unit production cost' => ['unit_production_cost', '-0.01'],
            'negative total revenue' => ['total_revenue', '-0.01'],
            'negative total production cost' => ['total_production_cost', '-0.01'],
        ];
    }

    #[DataProvider('invalidDistributionValues')]
    public function test_invalid_distribution_value_is_rejected(
        string $field,
        string $value
    ): void {
        $version = $this->createSchemeVersion();
        $recipient = $this->createCompanyRecipient();
        $transaction = $this->createTransaction();
        $item = $this->createTransactionItem($transaction, version: $version);

        $this->expectCheckConstraintViolation(
            fn () => $this->createDistribution(
                $item,
                $version,
                $recipient,
                [$field => $value]
            )
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidDistributionValues(): array
    {
        return [
            'zero percentage' => ['percentage', '0.0000'],
            'percentage above one hundred' => ['percentage', '100.0001'],
            'negative amount' => ['amount', '-0.01'],
        ];
    }

    public function test_duplicate_item_recipient_distribution_is_rejected(): void
    {
        $version = $this->createSchemeVersion();
        $recipient = $this->createCompanyRecipient();
        $transaction = $this->createTransaction();
        $item = $this->createTransactionItem($transaction, version: $version);
        $this->createDistribution($item, $version, $recipient);

        $this->expectException(QueryException::class);

        $this->createDistribution($item, $version, $recipient);
    }

    public function test_product_soft_delete_preserves_transaction_item_snapshot(): void
    {
        $product = $this->createProduct();
        $transaction = $this->createTransaction();
        $item = $this->createTransactionItem($transaction, $product);

        $product->delete();
        $item->refresh();

        $this->assertSoftDeleted($product);
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame($product->name, $item->product_name_snapshot);
        $this->assertSame($product->sku, $item->product_sku_snapshot);
    }

    public function test_historically_referenced_rule_version_cannot_be_deleted(): void
    {
        $version = $this->createSchemeVersion();
        $transaction = $this->createTransaction();
        $this->createTransactionItem($transaction, version: $version);

        $this->expectException(QueryException::class);

        $version->delete();
    }

    public function test_distribution_referenced_recipient_cannot_be_deleted(): void
    {
        $version = $this->createSchemeVersion();
        $recipient = $this->createCompanyRecipient();
        $transaction = $this->createTransaction();
        $item = $this->createTransactionItem($transaction, version: $version);
        $this->createDistribution($item, $version, $recipient);

        $this->expectException(QueryException::class);

        $recipient->delete();
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function expectCheckConstraintViolation(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected PostgreSQL to reject a CHECK constraint violation.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('check constraint', strtolower($exception->getMessage()));
        }
    }
}
