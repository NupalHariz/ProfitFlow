<?php

namespace Tests\Feature\Actions\ProfitRecipients;

use App\Actions\ProfitRecipients\SetProfitRecipientActive;
use App\Actions\ProfitRecipients\UpdateProfitRecipient;
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

class HistoricalIntegrityTest extends ProfitRecipientActionTestCase
{
    public function test_profile_and_status_mutations_preserve_financial_history_byte_for_byte(): void
    {
        $investor = $this->investor([
            'name' => 'Independent Investor',
            'is_active' => true,
        ]);
        $recipient = $this->createManagedRecipient([
            'code' => 'ORIGINAL-CODE',
            'investor_id' => $investor->id,
            'display_name' => 'Original Recipient Name',
        ]);
        $draftVersion = ProfitSchemeVersion::factory()
            ->general()
            ->create();
        $draftAllocation = ProfitSchemeAllocation::factory()
            ->for($draftVersion, 'version')
            ->for($recipient, 'recipient')
            ->create(['percentage' => '25.0000']);
        $publishedVersion = ProfitSchemeVersion::factory()
            ->general()
            ->published()
            ->create();
        $publishedAllocation = ProfitSchemeAllocation::factory()
            ->for($publishedVersion, 'version')
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
            ->withAppliedRule($publishedVersion)
            ->create([
                'quantity' => 2,
                'unit_selling_price' => '150.00',
                'unit_production_cost' => '90.00',
            ]);
        $distribution = ProfitDistribution::factory()
            ->for($transaction)
            ->for($item, 'transactionItem')
            ->for($publishedVersion, 'profitSchemeVersion')
            ->for($recipient, 'recipient')
            ->create([
                'recipient_name_snapshot' => 'Frozen Distribution Name',
                'percentage' => '100.0000',
                'amount' => '120.00',
            ]);
        $storedRows = [
            'investor' => $this->storedRow('investors', $investor->id),
            'draft_version' => $this->storedRow(
                'profit_scheme_versions',
                $draftVersion->id
            ),
            'draft_allocation' => $this->storedRow(
                'profit_scheme_allocations',
                $draftAllocation->id
            ),
            'published_version' => $this->storedRow(
                'profit_scheme_versions',
                $publishedVersion->id
            ),
            'published_allocation' => $this->storedRow(
                'profit_scheme_allocations',
                $publishedAllocation->id
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
                    "Recipient CRUD must not resolve [{$service}]."
                )
            );
        }

        $updated = app(UpdateProfitRecipient::class)->execute(
            $recipient,
            [
                'code' => 'CURRENT-CODE',
                'display_name' => 'Current Recipient Name',
            ]
        );
        $inactive = app(SetProfitRecipientActive::class)->execute(
            $updated,
            ['is_active' => false]
        );
        $active = app(SetProfitRecipientActive::class)->execute(
            $inactive,
            ['is_active' => true]
        );

        $this->assertSame($recipient->id, $active->id);
        $this->assertSame($investor->id, $active->investor_id);
        $this->assertSame('CURRENT-CODE', $active->code);
        $this->assertSame('Current Recipient Name', $active->display_name);
        $this->assertTrue($active->is_active);

        foreach ($storedRows as $key => $stored) {
            [$table, $id] = match ($key) {
                'investor' => ['investors', $investor->id],
                'draft_version' => [
                    'profit_scheme_versions',
                    $draftVersion->id,
                ],
                'draft_allocation' => [
                    'profit_scheme_allocations',
                    $draftAllocation->id,
                ],
                'published_version' => [
                    'profit_scheme_versions',
                    $publishedVersion->id,
                ],
                'published_allocation' => [
                    'profit_scheme_allocations',
                    $publishedAllocation->id,
                ],
                'transaction' => ['transactions', $transaction->id],
                'item' => ['transaction_items', $item->id],
                'distribution' => [
                    'profit_distributions',
                    $distribution->id,
                ],
            };

            $this->assertEquals($stored, $this->storedRow($table, $id));
        }

        $this->assertSame(
            'Frozen Distribution Name',
            $distribution->fresh()?->recipient_name_snapshot
        );
        Queue::assertNothingPushed();
    }

    private function storedRow(string $table, int $id): object
    {
        return DB::table($table)->where('id', $id)->firstOrFail();
    }
}
