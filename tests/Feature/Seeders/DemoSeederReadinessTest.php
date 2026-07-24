<?php

namespace Tests\Feature\Seeders;

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
use App\Queries\Dashboard\DashboardOverviewQuery;
use App\Services\ProfitSharing\FixedDecimal;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DemoSeederReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeat_seeding_preserves_immutable_history_and_creates_no_duplicates_or_jobs(): void
    {
        Queue::fake();

        $this->seed(DatabaseSeeder::class);
        $before = $this->immutableHistory();
        $counts = $this->canonicalCounts();

        $this->assertSame([
            'categories' => 3,
            'products' => 6,
            'customers' => 4,
            'investors' => 3,
            'recipients' => 4,
            'schemes' => 3,
            'versions' => 4,
            'allocations' => 11,
            'transactions' => 12,
            'items' => 14,
            'distributions' => 17,
        ], $counts);
        $this->assertSame(
            0,
            ProfitSchemeVersion::query()
                ->whereNull('product_category_id')
                ->count()
        );
        $this->assertDatabaseMissing('profit_schemes', [
            'name' => 'General Profit Sharing',
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, $this->canonicalCounts());
        $this->assertSame($before, $this->immutableHistory());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        Queue::assertNothingPushed();
    }

    public function test_fresh_seed_populates_every_dashboard_section_with_exact_ledger_totals(): void
    {
        Queue::fake();

        $this->seed(DatabaseSeeder::class);

        $overview = app(DashboardOverviewQuery::class)->get();
        $financials = $overview['financialSummary'];

        $this->assertSame([
            'totalRevenue' => '58550000.00',
            'totalProductionCost' => '44350000.00',
            'totalNetProfit' => '14200000.00',
            'totalDistributedProfit' => '13310000.00',
            'totalTransactions' => 12,
        ], $financials);
        $this->assertSame([
            'draft' => 2,
            'pending_distribution' => 1,
            'processing_distribution' => 0,
            'distributed' => 7,
            'distribution_failed' => 1,
            'cancelled' => 1,
        ], $overview['statusSummary']);
        $this->assertCount(8, $overview['recentTransactions']);
        $this->assertCount(1, $overview['recentFailedDistributions']);
        $this->assertCount(1, $overview['recentPendingDistributions']);
        $this->assertSame(
            'TRX-DEMO-011',
            $overview['recentFailedDistributions'][0]['transaction_number']
        );
        $this->assertSame(
            'TRX-DEMO-010',
            $overview['recentPendingDistributions'][0]['transaction_number']
        );
        $this->assertSame([
            'Aria Pratama',
            'Company Reserve',
            'Bima Santoso',
            'Citra Lestari',
        ], array_column($overview['topRecipients'], 'display_name'));
        $this->assertSame(
            ['investor', 'company', 'investor', 'investor'],
            array_column($overview['topRecipients'], 'type')
        );
        $this->assertSame(
            DB::table('profit_distributions')
                ->selectRaw('SUM(amount)::numeric(18, 2) AS total')
                ->value('total'),
            $financials['totalDistributedProfit']
        );
        $this->assertGreaterThan(
            0,
            FixedDecimal::moneyToMinorUnits(
                $financials['totalDistributedProfit']
            )
        );
        Queue::assertNothingPushed();
    }

    public function test_production_seeders_contain_no_queue_or_unsafe_arithmetic_or_cleanup_calls(): void
    {
        $source = collect(File::allFiles(database_path('seeders')))
            ->map(fn (\SplFileInfo $file): string => $file->getContents())
            ->implode("\n");

        foreach ([
            'dispatch(',
            'dispatchSync(',
            'Queue::',
            'parseFloat',
            'Math.round',
            'truncate(',
            'forceDelete(',
            'profit_distributions)->delete',
        ] as $prohibited) {
            $this->assertStringNotContainsString($prohibited, $source);
        }

        $this->assertDoesNotMatchRegularExpression(
            '/\b(?:float|double)\b|(?<![A-Za-z])round\(/',
            $source
        );
        $this->assertDoesNotMatchRegularExpression(
            '/ProfitDistribution::query\(\)->updateOrCreate/',
            $source
        );
    }

    /**
     * @return array<string, int>
     */
    private function canonicalCounts(): array
    {
        return [
            'categories' => ProductCategory::query()->count(),
            'products' => Product::query()->count(),
            'customers' => Customer::query()->count(),
            'investors' => Investor::query()->count(),
            'recipients' => ProfitRecipient::query()->count(),
            'schemes' => ProfitScheme::query()->count(),
            'versions' => ProfitSchemeVersion::query()->count(),
            'allocations' => ProfitSchemeAllocation::query()->count(),
            'transactions' => Transaction::query()->count(),
            'items' => TransactionItem::query()->count(),
            'distributions' => ProfitDistribution::query()->count(),
        ];
    }

    /**
     * @return array{
     *     transactions: list<array<string, mixed>>,
     *     items: list<array<string, mixed>>,
     *     distributions: list<array<string, mixed>>
     * }
     */
    private function immutableHistory(): array
    {
        return [
            'transactions' => DB::table('transactions')
                ->whereLike('transaction_number', 'TRX-DEMO-%')
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all(),
            'items' => DB::table('transaction_items')
                ->whereIn(
                    'transaction_id',
                    Transaction::query()
                        ->whereLike('transaction_number', 'TRX-DEMO-%')
                        ->select('id')
                )
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all(),
            'distributions' => DB::table('profit_distributions')
                ->whereIn(
                    'transaction_id',
                    Transaction::query()
                        ->whereLike('transaction_number', 'TRX-DEMO-%')
                        ->select('id')
                )
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all(),
        ];
    }
}
