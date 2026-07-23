<?php

namespace Tests\Feature\Factories;

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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_domain_factory_creates_a_valid_record(): void
    {
        $models = [
            ProductCategory::factory()->create(),
            Product::factory()->create(),
            Customer::factory()->create(),
            Investor::factory()->create(),
            ProfitRecipient::factory()->create(),
            ProfitScheme::factory()->create(),
            ProfitSchemeVersion::factory()->create(),
            ProfitSchemeAllocation::factory()->create(),
            Transaction::factory()->create(),
            TransactionItem::factory()->create(),
            ProfitDistribution::factory()->create(),
        ];

        foreach ($models as $model) {
            $this->assertTrue($model->exists);
        }
    }

    public function test_inactive_factory_states_disable_soft_deletable_master_data(): void
    {
        $this->assertFalse(ProductCategory::factory()->inactive()->create()->is_active);
        $this->assertFalse(Product::factory()->inactive()->create()->is_active);
        $this->assertFalse(Investor::factory()->inactive()->create()->is_active);
    }

    public function test_profit_recipient_states_satisfy_type_and_investor_constraints(): void
    {
        $investorRecipient = ProfitRecipient::factory()->create();
        $companyRecipient = ProfitRecipient::factory()->company()->create();

        $this->assertSame(ProfitRecipientType::Investor, $investorRecipient->type);
        $this->assertNotNull($investorRecipient->investor_id);
        $this->assertSame($investorRecipient->investor->name, $investorRecipient->display_name);

        $this->assertSame(ProfitRecipientType::Company, $companyRecipient->type);
        $this->assertNull($companyRecipient->investor_id);
        $this->assertNull($companyRecipient->investor);
    }

    public function test_profit_scheme_version_states_are_explicit_and_do_not_publish_allocations(): void
    {
        $category = ProductCategory::factory()->create();
        $effectiveDate = CarbonImmutable::parse('2026-07-15');

        $published = ProfitSchemeVersion::factory()->published()->create();
        $general = ProfitSchemeVersion::factory()->general()->create();
        $categorySpecific = ProfitSchemeVersion::factory()
            ->forCategory($category)
            ->effectiveOn($effectiveDate)
            ->create();

        $this->assertNotNull($published->published_at);
        $this->assertCount(0, $published->allocations);
        $this->assertNull($general->product_category_id);
        $this->assertSame($category->id, $categorySpecific->product_category_id);
        $this->assertSame('2026-07-15', $categorySpecific->effective_from->toDateString());
        $this->assertNull($categorySpecific->effective_until);
    }

    public function test_transaction_factory_defaults_to_a_clean_draft(): void
    {
        $transaction = Transaction::factory()->create();

        $this->assertSame(TransactionStatus::Draft, $transaction->status);
        $this->assertNull($transaction->idempotency_key);
        $this->assertSame('0.00', $transaction->total_revenue);
        $this->assertSame('0.00', $transaction->total_production_cost);
        $this->assertSame('0.00', $transaction->total_net_profit);
        $this->assertNull($transaction->completed_at);
        $this->assertNull($transaction->distribution_started_at);
        $this->assertNull($transaction->distributed_at);
        $this->assertNull($transaction->cancelled_at);
        $this->assertCount(0, $transaction->items);
        $this->assertCount(0, $transaction->distributions);
    }

    public function test_transaction_lifecycle_states_have_coherent_timestamps(): void
    {
        $pending = Transaction::factory()->pendingDistribution()->create();
        $processing = Transaction::factory()->processingDistribution()->create();
        $distributed = Transaction::factory()->distributed()->create();
        $failed = Transaction::factory()->distributionFailed()->create();
        $cancelled = Transaction::factory()->cancelled()->create();

        $this->assertSame(TransactionStatus::PendingDistribution, $pending->status);
        $this->assertNotNull($pending->completed_by_user_id);
        $this->assertNotNull($pending->completed_at);
        $this->assertNull($pending->distribution_started_at);

        $this->assertSame(TransactionStatus::ProcessingDistribution, $processing->status);
        $this->assertTrue($processing->completed_at->lessThan($processing->distribution_started_at));
        $this->assertNull($processing->distributed_at);

        $this->assertSame(TransactionStatus::Distributed, $distributed->status);
        $this->assertTrue($distributed->completed_at->lessThan($distributed->distribution_started_at));
        $this->assertTrue($distributed->distribution_started_at->lessThan($distributed->distributed_at));

        $this->assertSame(TransactionStatus::DistributionFailed, $failed->status);
        $this->assertNotNull($failed->completed_at);
        $this->assertNotNull($failed->distribution_started_at);
        $this->assertNull($failed->distributed_at);
        $this->assertNotNull($failed->failure_reason);

        $this->assertSame(TransactionStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertNull($cancelled->completed_at);
        $this->assertNull($cancelled->distribution_started_at);
        $this->assertNull($cancelled->distributed_at);
    }

    public function test_transaction_item_factory_copies_snapshots_and_calculates_minor_units(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Snapshot Category']);
        $product = Product::factory()->for($category, 'category')->create([
            'name' => 'Snapshot Product',
            'sku' => 'SNAPSHOT-SKU',
            'default_selling_price' => '125.50',
            'default_production_cost' => '80.25',
        ]);

        $item = TransactionItem::factory()->for($product)->create([
            'quantity' => 3,
        ]);

        $this->assertSame($product->product_category_id, $item->product_category_id);
        $this->assertSame('Snapshot Product', $item->product_name_snapshot);
        $this->assertSame('SNAPSHOT-SKU', $item->product_sku_snapshot);
        $this->assertSame('Snapshot Category', $item->category_name_snapshot);
        $this->assertSame('125.50', $item->unit_selling_price);
        $this->assertSame('80.25', $item->unit_production_cost);
        $this->assertSame('376.50', $item->total_revenue);
        $this->assertSame('240.75', $item->total_production_cost);
        $this->assertSame('135.75', $item->net_profit);
    }

    public function test_transaction_item_profit_states_have_the_expected_sign(): void
    {
        $profitable = TransactionItem::factory()->profitable()->create();
        $breakEven = TransactionItem::factory()->breakEven()->create();
        $lossMaking = TransactionItem::factory()->lossMaking()->create();

        $this->assertSame('100.00', $profitable->net_profit);
        $this->assertSame('0.00', $breakEven->net_profit);
        $this->assertSame('-50.00', $lossMaking->net_profit);
    }

    public function test_applied_rule_and_distribution_factories_keep_references_consistent(): void
    {
        $version = ProfitSchemeVersion::factory()->published()->create();
        $item = TransactionItem::factory()->withAppliedRule($version)->create();
        $distribution = ProfitDistribution::factory()->create();

        $this->assertSame($version->id, $item->applied_profit_scheme_version_id);
        $this->assertNotNull($item->rule_resolved_at);
        $this->assertSame($distribution->transactionItem->transaction_id, $distribution->transaction_id);
        $this->assertSame(
            $distribution->transactionItem->applied_profit_scheme_version_id,
            $distribution->profit_scheme_version_id
        );
        $this->assertSame(
            $distribution->recipient->display_name,
            $distribution->recipient_name_snapshot
        );
        $this->assertSame(TransactionStatus::Draft, $distribution->transaction->status);
    }
}
