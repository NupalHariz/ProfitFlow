<?php

namespace Tests\Feature\Actions\Investors;

use App\Actions\Investors\SetInvestorActive;
use App\Actions\Investors\UpdateInvestor;
use App\Models\Product;
use App\Models\ProfitDistribution;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\ProfitSharing\ProfitAllocator;
use App\Services\ProfitSharing\ProfitCalculator;
use App\Services\ProfitSharing\ProfitDistributor;
use App\Services\ProfitSharing\ProfitRuleResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class HistoricalIntegrityTest extends InvestorActionTestCase
{
    public function test_profile_and_status_lifecycle_preserves_recipient_and_financial_history(): void
    {
        $investor = $this->createManagedInvestor([
            'name' => 'Original Investor Name',
        ]);
        $recipient = $this->recipient($investor, [
            'code' => 'INVESTOR-HISTORY',
            'display_name' => 'Frozen Allocation Identity',
            'is_active' => true,
        ]);
        $version = ProfitSchemeVersion::factory()
            ->published()
            ->create();
        $allocation = ProfitSchemeAllocation::factory()
            ->for($version, 'version')
            ->for($recipient, 'recipient')
            ->create(['percentage' => '100.0000']);
        $product = Product::factory()->create();
        $transaction = Transaction::factory()
            ->distributed()
            ->create([
                'total_revenue' => '300.00',
                'total_production_cost' => '180.00',
                'total_net_profit' => '120.00',
            ]);
        $item = TransactionItem::factory()
            ->for($transaction)
            ->for($product)
            ->withAppliedRule($version)
            ->create([
                'quantity' => 2,
                'unit_selling_price' => '150.00',
                'unit_production_cost' => '90.00',
            ]);
        $distribution = ProfitDistribution::factory()
            ->for($transaction)
            ->for($item, 'transactionItem')
            ->for($version, 'profitSchemeVersion')
            ->for($recipient, 'recipient')
            ->create([
                'recipient_name_snapshot' => 'Frozen Distribution Name',
                'percentage' => '100.0000',
                'amount' => '120.00',
            ]);
        $storedRows = [
            'recipient' => $this->storedRow(
                'profit_recipients',
                $recipient->id
            ),
            'allocation' => $this->storedRow(
                'profit_scheme_allocations',
                $allocation->id
            ),
            'version' => $this->storedRow(
                'profit_scheme_versions',
                $version->id
            ),
            'transaction' => $this->storedRow(
                'transactions',
                $transaction->id
            ),
            'item' => $this->storedRow('transaction_items', $item->id),
            'distribution' => $this->storedRow(
                'profit_distributions',
                $distribution->id
            ),
        ];

        Queue::fake();
        foreach ([
            ProfitCalculator::class,
            ProfitRuleResolver::class,
            ProfitAllocator::class,
            ProfitDistributor::class,
        ] as $service) {
            app()->resolving(
                $service,
                fn (): never => $this->fail(
                    "Investor lifecycle actions must not resolve [{$service}]."
                )
            );
        }

        $updated = app(UpdateInvestor::class)->execute($investor, [
            'name' => 'Current Investor Name',
            'email' => 'current@example.test',
            'phone' => '+62 811 9999 0001',
        ]);
        $inactive = app(SetInvestorActive::class)->execute(
            $updated,
            ['is_active' => false]
        );
        $active = app(SetInvestorActive::class)->execute(
            $inactive,
            ['is_active' => true]
        );

        $this->assertSame('Current Investor Name', $active->name);
        $this->assertSame('current@example.test', $active->email);
        $this->assertSame('081199990001', $active->phone);
        $this->assertTrue($active->is_active);
        $this->assertEquals(
            $storedRows['recipient'],
            $this->storedRow('profit_recipients', $recipient->id)
        );
        $this->assertEquals(
            $storedRows['allocation'],
            $this->storedRow(
                'profit_scheme_allocations',
                $allocation->id
            )
        );
        $this->assertEquals(
            $storedRows['version'],
            $this->storedRow('profit_scheme_versions', $version->id)
        );
        $this->assertEquals(
            $storedRows['transaction'],
            $this->storedRow('transactions', $transaction->id)
        );
        $this->assertEquals(
            $storedRows['item'],
            $this->storedRow('transaction_items', $item->id)
        );
        $this->assertEquals(
            $storedRows['distribution'],
            $this->storedRow(
                'profit_distributions',
                $distribution->id
            )
        );
        Queue::assertNothingPushed();
    }

    private function storedRow(string $table, int $id): object
    {
        return DB::table($table)->where('id', $id)->firstOrFail();
    }
}
