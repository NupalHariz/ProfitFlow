<?php

namespace Tests\Feature\Database;

use App\Enums\ProfitRecipientType;
use App\Enums\TransactionStatus;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainRecords;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    public function test_complete_relationship_graph_can_be_created_and_loaded(): void
    {
        $user = $this->createUser();
        $category = $this->createCategory();
        $product = $this->createProduct($category);
        $customer = $this->createCustomer();
        $investor = $this->createInvestor();
        $recipient = $this->createInvestorRecipient($investor);
        $scheme = $this->createScheme($user);
        $version = $this->createSchemeVersion($scheme, $user, [
            'product_category_id' => $category->id,
        ]);
        $allocation = $this->createAllocation($version, $recipient);
        $transaction = $this->createTransaction($customer, $user, [
            'completed_by_user_id' => $user->id,
        ]);
        $item = $this->createTransactionItem($transaction, $product, $version);
        $distribution = $this->createDistribution($item, $version, $recipient);

        $this->assertTrue($category->products->contains($product));
        $this->assertTrue($category->profitSchemeVersions->contains($version));
        $this->assertTrue($category->transactionItems->contains($item));
        $this->assertTrue($product->category->is($category));
        $this->assertTrue($product->transactionItems->contains($item));
        $this->assertTrue($customer->transactions->contains($transaction));
        $this->assertTrue($investor->profitRecipient->is($recipient));
        $this->assertTrue($recipient->investor->is($investor));
        $this->assertTrue($recipient->allocations->contains($allocation));
        $this->assertTrue($recipient->distributions->contains($distribution));
        $this->assertTrue($scheme->versions->contains($version));
        $this->assertTrue($scheme->creator->is($user));
        $this->assertTrue($version->scheme->is($scheme));
        $this->assertTrue($version->category->is($category));
        $this->assertTrue($version->allocations->contains($allocation));
        $this->assertTrue($version->creator->is($user));
        $this->assertTrue($version->appliedTransactionItems->contains($item));
        $this->assertTrue($version->distributions->contains($distribution));
        $this->assertTrue($allocation->version->is($version));
        $this->assertTrue($allocation->recipient->is($recipient));
        $this->assertTrue($transaction->customer->is($customer));
        $this->assertTrue($transaction->creator->is($user));
        $this->assertTrue($transaction->completer->is($user));
        $this->assertTrue($transaction->items->contains($item));
        $this->assertTrue($transaction->distributions->contains($distribution));
        $this->assertTrue($item->transaction->is($transaction));
        $this->assertTrue($item->product->is($product));
        $this->assertTrue($item->category->is($category));
        $this->assertTrue($item->appliedProfitSchemeVersion->is($version));
        $this->assertTrue($item->distributions->contains($distribution));
        $this->assertTrue($distribution->transaction->is($transaction));
        $this->assertTrue($distribution->transactionItem->is($item));
        $this->assertTrue($distribution->profitSchemeVersion->is($version));
        $this->assertTrue($distribution->recipient->is($recipient));
        $this->assertTrue($user->createdProfitSchemes->contains($scheme));
        $this->assertTrue($user->createdProfitSchemeVersions->contains($version));
        $this->assertTrue($user->createdTransactions->contains($transaction));
        $this->assertTrue($user->completedTransactions->contains($transaction));
    }

    public function test_enum_decimal_and_date_casts_preserve_domain_types_and_precision(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct(attributes: [
            'default_selling_price' => '12.3',
            'default_production_cost' => '4.5',
        ]);
        $recipient = $this->createCompanyRecipient();
        $version = $this->createSchemeVersion(creator: $user, attributes: [
            'minimum_profit' => '-5.5',
            'maximum_profit' => '25',
            'effective_until' => '2026-07-31',
            'published_at' => '2026-07-01 09:30:00',
        ]);
        $allocation = $this->createAllocation($version, $recipient, [
            'percentage' => '33.33',
        ]);
        $transaction = $this->createTransaction(creator: $user, attributes: [
            'status' => TransactionStatus::PendingDistribution,
            'total_revenue' => '12.3',
            'total_production_cost' => '4.5',
            'total_net_profit' => '7.8',
        ]);
        $item = $this->createTransactionItem($transaction, $product, $version);
        $distribution = $this->createDistribution($item, $version, $recipient, [
            'percentage' => '33.33',
            'amount' => '2.25',
        ]);

        $this->assertSame(ProfitRecipientType::Company, $recipient->fresh()->type);
        $this->assertSame(
            TransactionStatus::PendingDistribution,
            $transaction->fresh()->status
        );
        $this->assertSame('12.30', $product->fresh()->default_selling_price);
        $this->assertSame('4.50', $product->fresh()->default_production_cost);
        $this->assertSame('-5.50', $version->fresh()->minimum_profit);
        $this->assertSame('25.00', $version->fresh()->maximum_profit);
        $this->assertSame('33.3300', $allocation->fresh()->percentage);
        $this->assertSame('7.80', $transaction->fresh()->total_net_profit);
        $this->assertSame('90.50', $item->fresh()->net_profit);
        $this->assertSame('33.3300', $distribution->fresh()->percentage);
        $this->assertSame('2.25', $distribution->fresh()->amount);
        $this->assertInstanceOf(CarbonInterface::class, $version->fresh()->effective_from);
        $this->assertInstanceOf(CarbonInterface::class, $version->fresh()->effective_until);
        $this->assertInstanceOf(CarbonInterface::class, $version->fresh()->published_at);
        $this->assertInstanceOf(CarbonInterface::class, $transaction->fresh()->transaction_date);
    }
}
