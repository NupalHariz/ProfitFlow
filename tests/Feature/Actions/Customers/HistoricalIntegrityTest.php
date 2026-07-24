<?php

namespace Tests\Feature\Actions\Customers;

use App\Actions\Customers\DeleteCustomer;
use App\Actions\Customers\UpdateCustomer;
use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\ProfitDistribution;
use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\ProfitSharing\ProfitCalculator;
use App\Services\ProfitSharing\ProfitRuleResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class HistoricalIntegrityTest extends CustomerActionTestCase
{
    public function test_update_then_soft_delete_preserves_draft_and_distributed_history(): void
    {
        $customer = $this->createManagedCustomer([
            'name' => 'Original Customer',
        ]);
        $product = Product::factory()->create([
            'name' => 'Historical Product',
            'sku' => 'HISTORICAL-SKU',
            'default_selling_price' => '150.00',
            'default_production_cost' => '90.00',
        ]);
        $version = ProfitSchemeVersion::factory()
            ->forCategory($product->category)
            ->published()
            ->create();
        $draft = Transaction::factory()
            ->for($customer)
            ->create([
                'status' => TransactionStatus::Draft,
                'total_revenue' => '300.00',
                'total_production_cost' => '180.00',
                'total_net_profit' => '120.00',
            ]);
        $draftItem = TransactionItem::factory()
            ->for($draft)
            ->for($product)
            ->create([
                'quantity' => 2,
                'unit_selling_price' => '150.00',
                'unit_production_cost' => '90.00',
            ]);
        $distributed = Transaction::factory()
            ->for($customer)
            ->distributed()
            ->create([
                'total_revenue' => '300.00',
                'total_production_cost' => '180.00',
                'total_net_profit' => '120.00',
            ]);
        $distributedItem = TransactionItem::factory()
            ->for($distributed)
            ->for($product)
            ->withAppliedRule($version)
            ->create([
                'quantity' => 2,
                'unit_selling_price' => '150.00',
                'unit_production_cost' => '90.00',
            ]);
        $recipient = ProfitRecipient::factory()->company()->create();
        $distribution = ProfitDistribution::factory()
            ->for($distributed)
            ->for($distributedItem, 'transactionItem')
            ->for($version, 'profitSchemeVersion')
            ->for($recipient, 'recipient')
            ->create([
                'recipient_name_snapshot' => 'Frozen Company Recipient',
                'percentage' => '100.0000',
                'amount' => '120.00',
            ]);
        $storedRows = [
            'draft' => $this->storedRow('transactions', $draft->id),
            'draft_item' => $this->storedRow(
                'transaction_items',
                $draftItem->id
            ),
            'distributed' => $this->storedRow(
                'transactions',
                $distributed->id
            ),
            'distributed_item' => $this->storedRow(
                'transaction_items',
                $distributedItem->id
            ),
            'version' => $this->storedRow(
                'profit_scheme_versions',
                $version->id
            ),
            'distribution' => $this->storedRow(
                'profit_distributions',
                $distribution->id
            ),
        ];

        Queue::fake();
        app()->resolving(
            ProfitCalculator::class,
            fn (): never => $this->fail(
                'Customer lifecycle actions must not resolve the calculator.'
            )
        );
        app()->resolving(
            ProfitRuleResolver::class,
            fn (): never => $this->fail(
                'Customer lifecycle actions must not resolve the rule resolver.'
            )
        );

        app(UpdateCustomer::class)->execute(
            $customer,
            $this->customerData([
                'name' => 'Current Customer Name',
                'email' => 'current@example.test',
            ])
        );

        $this->assertSame(
            'Current Customer Name',
            $draft->fresh()?->customer?->name
        );
        $this->assertSame(
            'Current Customer Name',
            $distributed->fresh()?->customer?->name
        );
        $this->assertStoredRowsUnchanged(
            $storedRows,
            $draft,
            $draftItem,
            $distributed,
            $distributedItem,
            $version,
            $distribution
        );

        app(DeleteCustomer::class)->execute($customer);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertSame($customer->id, $draft->fresh()?->customer_id);
        $this->assertSame(
            'Current Customer Name',
            $draft->fresh()?->customer?->name
        );
        $this->assertTrue((bool) $draft->fresh()?->customer?->trashed());
        $this->assertSame(
            'Current Customer Name',
            $distributed->fresh()?->customer?->name
        );
        $this->assertTrue(
            (bool) $distributed->fresh()?->customer?->trashed()
        );
        $this->assertStoredRowsUnchanged(
            $storedRows,
            $draft,
            $draftItem,
            $distributed,
            $distributedItem,
            $version,
            $distribution
        );
        Queue::assertNothingPushed();
    }

    /**
     * @param  array<string, object>  $storedRows
     */
    private function assertStoredRowsUnchanged(
        array $storedRows,
        Transaction $draft,
        TransactionItem $draftItem,
        Transaction $distributed,
        TransactionItem $distributedItem,
        ProfitSchemeVersion $version,
        ProfitDistribution $distribution
    ): void {
        $this->assertEquals(
            $storedRows['draft'],
            $this->storedRow('transactions', $draft->id)
        );
        $this->assertEquals(
            $storedRows['draft_item'],
            $this->storedRow('transaction_items', $draftItem->id)
        );
        $this->assertEquals(
            $storedRows['distributed'],
            $this->storedRow('transactions', $distributed->id)
        );
        $this->assertEquals(
            $storedRows['distributed_item'],
            $this->storedRow(
                'transaction_items',
                $distributedItem->id
            )
        );
        $this->assertEquals(
            $storedRows['version'],
            $this->storedRow('profit_scheme_versions', $version->id)
        );
        $this->assertEquals(
            $storedRows['distribution'],
            $this->storedRow(
                'profit_distributions',
                $distribution->id
            )
        );
    }

    private function storedRow(string $table, int $id): object
    {
        return DB::table($table)->where('id', $id)->firstOrFail();
    }
}
