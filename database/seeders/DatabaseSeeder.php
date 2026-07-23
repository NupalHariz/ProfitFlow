<?php

namespace Database\Seeders;

use App\Enums\ProfitRecipientType;
use App\Models\Customer;
use App\Models\Investor;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProfitRecipient;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'ProfitFlow Administrator',
            'email_verified_at' => '2026-01-01 00:00:00',
            'password' => Hash::make('password'),
        ]);

        /** @var array<string, ProductCategory> $categories */
        $categories = [];

        foreach ([
            'electronics' => [
                'name' => 'Electronics',
                'description' => 'Electronic equipment and workplace technology.',
            ],
            'home-office' => [
                'name' => 'Home & Office',
                'description' => 'Furniture and equipment for productive workspaces.',
            ],
            'professional-services' => [
                'name' => 'Professional Services',
                'description' => 'Implementation, setup, and training services.',
            ],
        ] as $slug => $attributes) {
            $categories[$slug] = ProductCategory::query()
                ->withTrashed()
                ->updateOrCreate(['slug' => $slug], [
                    ...$attributes,
                    'is_active' => true,
                    'deleted_at' => null,
                ]);
        }

        foreach ([
            [
                'category' => 'electronics',
                'sku' => 'ELEC-ROUTER-01',
                'name' => 'Business Wireless Router',
                'selling_price' => '1800000.00',
                'production_cost' => '1200000.00',
            ],
            [
                'category' => 'electronics',
                'sku' => 'ELEC-LAPTOP-01',
                'name' => 'Office Laptop',
                'selling_price' => '12500000.00',
                'production_cost' => '9800000.00',
            ],
            [
                'category' => 'home-office',
                'sku' => 'OFFICE-CHAIR-01',
                'name' => 'Ergonomic Office Chair',
                'selling_price' => '3200000.00',
                'production_cost' => '2200000.00',
            ],
            [
                'category' => 'home-office',
                'sku' => 'OFFICE-DESK-01',
                'name' => 'Adjustable Standing Desk',
                'selling_price' => '5500000.00',
                'production_cost' => '3900000.00',
            ],
            [
                'category' => 'professional-services',
                'sku' => 'SERVICE-SETUP-01',
                'name' => 'Workplace Setup Service',
                'selling_price' => '1500000.00',
                'production_cost' => '500000.00',
            ],
            [
                'category' => 'professional-services',
                'sku' => 'SERVICE-TRAINING-01',
                'name' => 'Product Training Session',
                'selling_price' => '2500000.00',
                'production_cost' => '750000.00',
            ],
        ] as $product) {
            Product::query()->withTrashed()->updateOrCreate([
                'sku' => $product['sku'],
            ], [
                'product_category_id' => $categories[$product['category']]->id,
                'name' => $product['name'],
                'description' => null,
                'default_selling_price' => $product['selling_price'],
                'default_production_cost' => $product['production_cost'],
                'is_active' => true,
                'deleted_at' => null,
            ]);
        }

        foreach ([
            [
                'name' => 'PT Nusantara Retail',
                'email' => 'purchasing@nusantara-retail.test',
                'phone' => '+62 21 555 0101',
                'address' => 'Jakarta',
            ],
            [
                'name' => 'CV Mitra Digital',
                'email' => 'operations@mitra-digital.test',
                'phone' => '+62 22 555 0102',
                'address' => 'Bandung',
            ],
            [
                'name' => 'PT Solusi Kerja',
                'email' => 'finance@solusi-kerja.test',
                'phone' => '+62 31 555 0103',
                'address' => 'Surabaya',
            ],
            [
                'name' => 'Koperasi Maju Bersama',
                'email' => 'admin@maju-bersama.test',
                'phone' => '+62 274 555 0104',
                'address' => 'Yogyakarta',
            ],
        ] as $customer) {
            Customer::query()->withTrashed()->updateOrCreate([
                'email' => $customer['email'],
            ], [
                ...$customer,
                'deleted_at' => null,
            ]);
        }

        /** @var array<string, Investor> $investors */
        $investors = [];

        foreach ([
            'aria' => [
                'name' => 'Aria Pratama',
                'email' => 'aria.investor@example.com',
                'phone' => '+62 812 0000 1001',
            ],
            'bima' => [
                'name' => 'Bima Santoso',
                'email' => 'bima.investor@example.com',
                'phone' => '+62 812 0000 1002',
            ],
            'citra' => [
                'name' => 'Citra Lestari',
                'email' => 'citra.investor@example.com',
                'phone' => '+62 812 0000 1003',
            ],
        ] as $key => $investor) {
            $investors[$key] = Investor::query()
                ->withTrashed()
                ->updateOrCreate(['email' => $investor['email']], [
                    ...$investor,
                    'is_active' => true,
                    'deleted_at' => null,
                ]);
        }

        /** @var array<string, ProfitRecipient> $recipients */
        $recipients = [];

        foreach ($investors as $key => $investor) {
            $recipients[$key] = ProfitRecipient::query()->updateOrCreate([
                'investor_id' => $investor->id,
            ], [
                'code' => "investor-{$key}",
                'type' => ProfitRecipientType::Investor,
                'display_name' => $investor->name,
                'is_active' => true,
            ]);
        }

        $recipients['company'] = ProfitRecipient::query()->updateOrCreate([
            'code' => 'company',
        ], [
            'type' => ProfitRecipientType::Company,
            'investor_id' => null,
            'display_name' => 'Company',
            'is_active' => true,
        ]);

        $generalScheme = ProfitScheme::query()->updateOrCreate([
            'code' => 'general-profit-sharing',
        ], [
            'name' => 'General Profit Sharing',
            'description' => 'Default profit-sharing rule for all product categories.',
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $electronicsScheme = ProfitScheme::query()->updateOrCreate([
            'code' => 'electronics-growth',
        ], [
            'name' => 'Electronics Growth',
            'description' => 'Higher-priority sharing rule for electronics.',
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $generalVersion = ProfitSchemeVersion::query()->updateOrCreate([
            'profit_scheme_id' => $generalScheme->id,
            'version' => 1,
        ], [
            'product_category_id' => null,
            'minimum_profit' => '0.01',
            'maximum_profit' => null,
            'effective_from' => '2026-01-01',
            'effective_until' => null,
            'priority' => 10,
            'is_active' => true,
            'published_at' => '2026-01-01 00:00:00',
            'created_by_user_id' => $admin->id,
        ]);

        $electronicsVersion = ProfitSchemeVersion::query()->updateOrCreate([
            'profit_scheme_id' => $electronicsScheme->id,
            'version' => 1,
        ], [
            'product_category_id' => $categories['electronics']->id,
            'minimum_profit' => '100.00',
            'maximum_profit' => null,
            'effective_from' => '2026-01-01',
            'effective_until' => null,
            'priority' => 100,
            'is_active' => true,
            'published_at' => '2026-01-01 00:00:00',
            'created_by_user_id' => $admin->id,
        ]);

        foreach ([
            [$generalVersion, $recipients['aria'], '40.0000'],
            [$generalVersion, $recipients['bima'], '30.0000'],
            [$generalVersion, $recipients['company'], '30.0000'],
            [$electronicsVersion, $recipients['aria'], '50.0000'],
            [$electronicsVersion, $recipients['citra'], '20.0000'],
            [$electronicsVersion, $recipients['company'], '30.0000'],
        ] as [$version, $recipient, $percentage]) {
            ProfitSchemeAllocation::query()->updateOrCreate([
                'profit_scheme_version_id' => $version->id,
                'profit_recipient_id' => $recipient->id,
            ], [
                'percentage' => $percentage,
            ]);
        }
    }
}
