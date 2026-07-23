<?php

namespace Tests\Feature\Seeders;

use App\Enums\ProfitRecipientType;
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
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_required_minimal_development_baseline(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->sole();
        $company = ProfitRecipient::query()->where('code', 'company')->sole();
        $publishedVersions = ProfitSchemeVersion::query()
            ->whereNotNull('published_at')
            ->orderBy('id')
            ->get();

        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertSame(3, ProductCategory::query()->count());
        $this->assertSame(6, Product::query()->count());
        $this->assertSame(4, Customer::query()->count());
        $this->assertSame(3, Investor::query()->count());
        $this->assertSame(4, ProfitRecipient::query()->count());
        $this->assertSame(2, ProfitScheme::query()->count());
        $this->assertCount(2, $publishedVersions);
        $this->assertSame(6, ProfitSchemeAllocation::query()->count());
        $this->assertSame(ProfitRecipientType::Company, $company->type);
        $this->assertNull($company->investor_id);
        $this->assertSame('Company', $company->display_name);
        $this->assertSame(
            3,
            ProfitRecipient::query()
                ->where('type', ProfitRecipientType::Investor->value)
                ->count()
        );
        $this->assertSame(1, $publishedVersions->whereNull('product_category_id')->count());
        $this->assertSame(1, $publishedVersions->whereNotNull('product_category_id')->count());
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, ProfitDistribution::query()->count());

        foreach ($publishedVersions as $version) {
            $total = $version->allocations()
                ->selectRaw('SUM(percentage)::numeric(7, 4) AS aggregate')
                ->value('aggregate');

            $this->assertSame('100.0000', $total);
        }
    }

    public function test_seeder_is_idempotent_for_all_canonical_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $before = $this->canonicalCounts();

        $this->seed(DatabaseSeeder::class);
        $after = $this->canonicalCounts();

        $this->assertSame($before, $after);
        $this->assertSame([
            'users' => 1,
            'categories' => 3,
            'products' => 6,
            'customers' => 4,
            'investors' => 3,
            'recipients' => 4,
            'schemes' => 2,
            'versions' => 2,
            'allocations' => 6,
        ], $after);
    }

    /**
     * @return array<string, int>
     */
    private function canonicalCounts(): array
    {
        $schemeIds = ProfitScheme::query()
            ->whereIn('code', ['general-profit-sharing', 'electronics-growth'])
            ->pluck('id');
        $versionIds = ProfitSchemeVersion::query()
            ->whereIn('profit_scheme_id', $schemeIds)
            ->pluck('id');

        return [
            'users' => User::query()->where('email', 'admin@example.com')->count(),
            'categories' => ProductCategory::query()->whereIn('slug', [
                'electronics',
                'home-office',
                'professional-services',
            ])->count(),
            'products' => Product::query()->whereIn('sku', [
                'ELEC-ROUTER-01',
                'ELEC-LAPTOP-01',
                'OFFICE-CHAIR-01',
                'OFFICE-DESK-01',
                'SERVICE-SETUP-01',
                'SERVICE-TRAINING-01',
            ])->count(),
            'customers' => Customer::query()->whereIn('email', [
                'purchasing@nusantara-retail.test',
                'operations@mitra-digital.test',
                'finance@solusi-kerja.test',
                'admin@maju-bersama.test',
            ])->count(),
            'investors' => Investor::query()->whereIn('email', [
                'aria.investor@example.com',
                'bima.investor@example.com',
                'citra.investor@example.com',
            ])->count(),
            'recipients' => ProfitRecipient::query()->whereIn('code', [
                'investor-aria',
                'investor-bima',
                'investor-citra',
                'company',
            ])->count(),
            'schemes' => $schemeIds->count(),
            'versions' => $versionIds->count(),
            'allocations' => ProfitSchemeAllocation::query()
                ->whereIn('profit_scheme_version_id', $versionIds)
                ->count(),
        ];
    }
}
