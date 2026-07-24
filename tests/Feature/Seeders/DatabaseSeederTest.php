<?php

namespace Tests\Feature\Seeders;

use App\Actions\ProfitSchemes\UpdateDraftProfitSchemeVersion;
use App\Enums\ProfitRecipientType;
use App\Exceptions\ProfitSchemes\ProfitSchemeVersionImmutable;
use App\Models\Customer;
use App\Models\Investor;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProfitRecipient;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_seed_creates_coherent_master_data_and_verified_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->sole();

        $this->assertSame(1, User::query()->count());
        $this->assertSame('ProfitFlow Administrator', $admin->name);
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertSame(3, ProductCategory::query()->count());
        $this->assertSame([
            'electronics',
            'home-office',
            'lifestyle',
        ], ProductCategory::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(6, Product::query()->count());
        $this->assertSame(4, Customer::query()->count());
        $this->assertSame(3, Investor::query()->count());
        $this->assertSame([
            'Aria Pratama',
            'Bima Santoso',
            'Citra Lestari',
        ], Investor::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame([
            '0215550101',
            '0225550102',
            '02745550104',
        ], Customer::query()
            ->whereNotNull('phone')
            ->orderBy('phone')
            ->pluck('phone')
            ->all());
        $this->assertSame([
            '081200001001',
            '081200001002',
            '081200001003',
        ], Investor::query()->orderBy('phone')->pluck('phone')->all());

        foreach ([
            ...Customer::query()->whereNotNull('phone')->pluck('phone'),
            ...Investor::query()->whereNotNull('phone')->pluck('phone'),
        ] as $phone) {
            $this->assertMatchesRegularExpression(
                '/^0[1-9][0-9]{7,12}$/',
                $phone
            );
        }

        foreach ($this->expectedProducts() as $sku => $expected) {
            $product = Product::query()->where('sku', $sku)->sole();

            $this->assertSame($expected['name'], $product->name);
            $this->assertSame(
                $expected['category'],
                $product->category->slug
            );
            $this->assertSame(
                $expected['selling_price'],
                $product->default_selling_price
            );
            $this->assertSame(
                $expected['production_cost'],
                $product->default_production_cost
            );
        }

        $this->assertSame(4, ProfitRecipient::query()->count());
        $this->assertSame(
            3,
            ProfitRecipient::query()
                ->where('type', ProfitRecipientType::Investor->value)
                ->count()
        );

        foreach (ProfitRecipient::query()
            ->where('type', ProfitRecipientType::Investor->value)
            ->get() as $recipient) {
            $this->assertNotNull($recipient->investor_id);
            $this->assertNotNull($recipient->investor);
        }

        $company = ProfitRecipient::query()->where('code', 'company')->sole();

        $this->assertSame(ProfitRecipientType::Company, $company->type);
        $this->assertNull($company->investor_id);
        $this->assertSame('Company Reserve', $company->display_name);
    }

    public function test_demo_schemes_match_categories_and_published_allocations_are_exact(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, ProfitScheme::query()->count());
        $this->assertSame([
            'Electronics Growth',
            'Home & Office Growth',
            'Lifestyle Growth',
        ], ProfitScheme::query()->orderBy('name')->pluck('name')->all());

        $electronics = ProfitScheme::query()
            ->where('code', 'electronics-growth')
            ->sole();
        $homeOffice = ProfitScheme::query()
            ->where('code', 'home-office-growth')
            ->sole();
        $lifestyle = ProfitScheme::query()
            ->where('code', 'lifestyle-growth')
            ->sole();
        $electronicsVersions = $electronics->versions()
            ->orderBy('version')
            ->get();

        $this->assertCount(2, $electronicsVersions);
        $this->assertSame([1, 2], $electronicsVersions->pluck('version')->all());
        $this->assertSame(
            ['2026-07-01', '2026-07-15'],
            $electronicsVersions
                ->map(fn (ProfitSchemeVersion $version): string => $version
                    ->effective_from->toDateString())
                ->all()
        );
        $this->assertSame(
            '2026-07-14',
            $electronicsVersions[0]->effective_until?->toDateString()
        );
        $this->assertNull($electronicsVersions[1]->effective_until);
        $this->assertSame(
            ['electronics'],
            $electronicsVersions
                ->load('category')
                ->pluck('category.slug')
                ->unique()
                ->values()
                ->all()
        );
        $this->assertSame(
            ['home-office'],
            $homeOffice->versions()
                ->with('category')
                ->get()
                ->pluck('category.slug')
                ->unique()
                ->values()
                ->all()
        );
        $this->assertSame(
            0,
            $electronics->versions()
                ->whereHas(
                    'category',
                    fn ($query) => $query->where('slug', 'home-office')
                )
                ->count()
        );
        $this->assertSame(
            'lifestyle',
            $lifestyle->versions()
                ->with('category')
                ->sole()
                ->category
                ->slug
        );
        $this->assertSame(
            0,
            ProfitSchemeVersion::query()
                ->whereNull('product_category_id')
                ->count()
        );
        $this->assertDatabaseMissing('profit_schemes', [
            'name' => 'General Profit Sharing',
        ]);

        $publishedVersions = ProfitSchemeVersion::query()
            ->whereNotNull('published_at')
            ->with('allocations.recipient')
            ->orderBy('id')
            ->get();

        $this->assertCount(4, $publishedVersions);
        $this->assertSame(11, ProfitSchemeAllocation::query()->count());

        foreach ($publishedVersions as $version) {
            $this->assertNotEmpty($version->allocations);
            $this->assertSame(
                $version->allocations->count(),
                $version->allocations
                    ->pluck('profit_recipient_id')
                    ->unique()
                    ->count()
            );
            $this->assertSame(
                '100.0000',
                $version->allocations()
                    ->selectRaw(
                        'SUM(percentage)::numeric(7, 4) AS aggregate'
                    )
                    ->value('aggregate')
            );

            foreach ($version->allocations as $allocation) {
                $recipient = $allocation->recipient;

                $this->assertNotNull($recipient);

                if ($recipient->type === ProfitRecipientType::Investor) {
                    $this->assertNotNull($recipient->investor_id);
                } else {
                    $this->assertNull($recipient->investor_id);
                }
            }
        }
    }

    public function test_published_demo_versions_use_the_existing_immutability_guard(): void
    {
        $this->seed(DatabaseSeeder::class);

        $version = ProfitSchemeVersion::query()
            ->whereNotNull('published_at')
            ->firstOrFail();

        $this->expectException(ProfitSchemeVersionImmutable::class);

        app(UpdateDraftProfitSchemeVersion::class)->execute($version, []);
    }

    /**
     * @return array<string, array{
     *     name: string,
     *     category: string,
     *     selling_price: string,
     *     production_cost: string
     * }>
     */
    private function expectedProducts(): array
    {
        return [
            'ELEC-LAPTOP-01' => [
                'name' => 'Office Laptop',
                'category' => 'electronics',
                'selling_price' => '12500000.00',
                'production_cost' => '9800000.00',
            ],
            'ELEC-HEADPHONES-01' => [
                'name' => 'Wireless Headphones',
                'category' => 'electronics',
                'selling_price' => '1800000.00',
                'production_cost' => '1100000.00',
            ],
            'OFFICE-DESK-01' => [
                'name' => 'Adjustable Standing Desk',
                'category' => 'home-office',
                'selling_price' => '5500000.00',
                'production_cost' => '3900000.00',
            ],
            'OFFICE-CHAIR-01' => [
                'name' => 'Ergonomic Office Chair',
                'category' => 'home-office',
                'selling_price' => '3200000.00',
                'production_cost' => '2200000.00',
            ],
            'LIFE-BACKPACK-01' => [
                'name' => 'Travel Backpack',
                'category' => 'lifestyle',
                'selling_price' => '1200000.00',
                'production_cost' => '650000.00',
            ],
            'LIFE-BOTTLE-01' => [
                'name' => 'Smart Water Bottle',
                'category' => 'lifestyle',
                'selling_price' => '650000.00',
                'production_cost' => '280000.00',
            ],
        ];
    }
}
