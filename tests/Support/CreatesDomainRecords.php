<?php

namespace Tests\Support;

use App\Enums\ProfitRecipientType;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Investor;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProfitDistribution;
use App\Models\ProfitRecipient;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;

trait CreatesDomainRecords
{
    private int $domainRecordSequence = 0;

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createCategory(array $attributes = []): ProductCategory
    {
        $token = $this->nextDomainToken();

        return ProductCategory::query()->create(array_merge([
            'name' => "Category {$token}",
            'slug' => "category-{$token}",
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createProduct(
        ?ProductCategory $category = null,
        array $attributes = []
    ): Product {
        $token = $this->nextDomainToken();
        $category ??= $this->createCategory();

        return Product::query()->create(array_merge([
            'product_category_id' => $category->id,
            'name' => "Product {$token}",
            'sku' => "SKU-{$token}",
            'default_selling_price' => '125.50',
            'default_production_cost' => '80.25',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createCustomer(array $attributes = []): Customer
    {
        $token = $this->nextDomainToken();

        return Customer::query()->create(array_merge([
            'name' => "Customer {$token}",
            'email' => "customer-{$token}@example.com",
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createInvestor(array $attributes = []): Investor
    {
        $token = $this->nextDomainToken();

        return Investor::query()->create(array_merge([
            'name' => "Investor {$token}",
            'email' => "investor-{$token}@example.com",
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createInvestorRecipient(
        ?Investor $investor = null,
        array $attributes = []
    ): ProfitRecipient {
        $token = $this->nextDomainToken();
        $investor ??= $this->createInvestor();

        return ProfitRecipient::query()->create(array_merge([
            'code' => "INVESTOR-{$token}",
            'type' => ProfitRecipientType::Investor,
            'investor_id' => $investor->id,
            'display_name' => $investor->name,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createCompanyRecipient(array $attributes = []): ProfitRecipient
    {
        $token = $this->nextDomainToken();

        return ProfitRecipient::query()->create(array_merge([
            'code' => "COMPANY-{$token}",
            'type' => ProfitRecipientType::Company,
            'investor_id' => null,
            'display_name' => "Company {$token}",
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createScheme(
        ?User $creator = null,
        array $attributes = []
    ): ProfitScheme {
        $token = $this->nextDomainToken();
        $creator ??= $this->createUser();

        return ProfitScheme::query()->create(array_merge([
            'name' => "Scheme {$token}",
            'code' => "SCHEME-{$token}",
            'created_by_user_id' => $creator->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createSchemeVersion(
        ?ProfitScheme $scheme = null,
        ?User $creator = null,
        array $attributes = []
    ): ProfitSchemeVersion {
        $creator ??= $this->createUser();
        $scheme ??= $this->createScheme($creator);

        if (! array_key_exists('product_category_id', $attributes)) {
            $attributes['product_category_id'] = $this->createCategory()->id;
        }

        return ProfitSchemeVersion::query()->create(array_merge([
            'profit_scheme_id' => $scheme->id,
            'version' => 1,
            'effective_from' => '2026-07-01',
            'priority' => 0,
            'created_by_user_id' => $creator->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createAllocation(
        ProfitSchemeVersion $version,
        ProfitRecipient $recipient,
        array $attributes = []
    ): ProfitSchemeAllocation {
        return ProfitSchemeAllocation::query()->create(array_merge([
            'profit_scheme_version_id' => $version->id,
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createTransaction(
        ?Customer $customer = null,
        ?User $creator = null,
        array $attributes = []
    ): Transaction {
        $token = $this->nextDomainToken();
        $customer ??= $this->createCustomer();
        $creator ??= $this->createUser();

        return Transaction::query()->create(array_merge([
            'transaction_number' => "TRX-{$token}",
            'customer_id' => $customer->id,
            'transaction_date' => '2026-07-23',
            'status' => TransactionStatus::Draft,
            'total_revenue' => '0.00',
            'total_production_cost' => '0.00',
            'total_net_profit' => '0.00',
            'created_by_user_id' => $creator->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createTransactionItem(
        Transaction $transaction,
        ?Product $product = null,
        ?ProfitSchemeVersion $version = null,
        array $attributes = []
    ): TransactionItem {
        $product ??= $this->createProduct();

        return TransactionItem::query()->create(array_merge([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'product_category_id' => $product->product_category_id,
            'applied_profit_scheme_version_id' => $version?->id,
            'product_name_snapshot' => $product->name,
            'product_sku_snapshot' => $product->sku,
            'category_name_snapshot' => $product->category->name,
            'quantity' => 2,
            'unit_selling_price' => '125.50',
            'unit_production_cost' => '80.25',
            'total_revenue' => '251.00',
            'total_production_cost' => '160.50',
            'net_profit' => '90.50',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createDistribution(
        TransactionItem $item,
        ProfitSchemeVersion $version,
        ProfitRecipient $recipient,
        array $attributes = []
    ): ProfitDistribution {
        return ProfitDistribution::query()->create(array_merge([
            'transaction_id' => $item->transaction_id,
            'transaction_item_id' => $item->id,
            'profit_scheme_version_id' => $version->id,
            'profit_recipient_id' => $recipient->id,
            'recipient_name_snapshot' => $recipient->display_name,
            'percentage' => '100.0000',
            'amount' => '90.50',
        ], $attributes));
    }

    private function nextDomainToken(): string
    {
        $this->domainRecordSequence++;

        return sprintf('%02d-%s', $this->domainRecordSequence, spl_object_id($this));
    }
}
