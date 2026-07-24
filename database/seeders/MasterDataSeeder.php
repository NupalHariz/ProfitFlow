<?php

namespace Database\Seeders;

use App\Enums\ProfitRecipientType;
use App\Models\Customer;
use App\Models\Investor;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProfitRecipient;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class MasterDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function (): void {
            User::query()->updateOrCreate([
                'email' => 'admin@example.com',
            ], [
                'name' => 'ProfitFlow Administrator',
                'email_verified_at' => '2026-07-01 00:00:00',
                'password' => Hash::make('password'),
            ]);

            /** @var array<string, ProductCategory> $categories */
            $categories = [];

            foreach ($this->categories() as $slug => $attributes) {
                $categories[$slug] = ProductCategory::query()
                    ->withTrashed()
                    ->updateOrCreate(['slug' => $slug], [
                        ...$attributes,
                        'is_active' => true,
                        'deleted_at' => null,
                    ]);
            }

            foreach ($this->products() as $product) {
                Product::query()->withTrashed()->updateOrCreate([
                    'sku' => $product['sku'],
                ], [
                    'product_category_id' => $categories[
                        $product['category']
                    ]->id,
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'default_selling_price' => $product['selling_price'],
                    'default_production_cost' => $product['production_cost'],
                    'is_active' => true,
                    'deleted_at' => null,
                ]);
            }

            foreach ($this->customers() as $customer) {
                Customer::query()->withTrashed()->updateOrCreate([
                    'email' => $customer['email'],
                ], [
                    ...$customer,
                    'deleted_at' => null,
                ]);
            }

            /** @var array<string, Investor> $investors */
            $investors = [];

            foreach ($this->investors() as $key => $attributes) {
                $investors[$key] = Investor::query()
                    ->withTrashed()
                    ->updateOrCreate(['email' => $attributes['email']], [
                        ...$attributes,
                        'is_active' => true,
                        'deleted_at' => null,
                    ]);
            }

            foreach ($investors as $key => $investor) {
                ProfitRecipient::query()->updateOrCreate([
                    'investor_id' => $investor->id,
                ], [
                    'code' => "investor-{$key}",
                    'type' => ProfitRecipientType::Investor,
                    'display_name' => $investor->name,
                    'is_active' => true,
                ]);
            }

            ProfitRecipient::query()->updateOrCreate([
                'code' => 'company',
            ], [
                'type' => ProfitRecipientType::Company,
                'investor_id' => null,
                'display_name' => 'Company Reserve',
                'is_active' => true,
            ]);
        });
    }

    /**
     * @return array<string, array{name: string, description: string}>
     */
    private function categories(): array
    {
        return [
            'electronics' => [
                'name' => 'Electronics',
                'description' => 'Business technology and personal electronic devices.',
            ],
            'home-office' => [
                'name' => 'Home & Office',
                'description' => 'Furniture and equipment for productive workspaces.',
            ],
            'lifestyle' => [
                'name' => 'Lifestyle',
                'description' => 'Practical everyday products for travel and wellbeing.',
            ],
        ];
    }

    /**
     * @return list<array{
     *     category: string,
     *     sku: string,
     *     name: string,
     *     description: string,
     *     selling_price: string,
     *     production_cost: string
     * }>
     */
    private function products(): array
    {
        return [
            [
                'category' => 'electronics',
                'sku' => 'ELEC-LAPTOP-01',
                'name' => 'Office Laptop',
                'description' => 'Reliable business laptop for daily office workloads.',
                'selling_price' => '12500000.00',
                'production_cost' => '9800000.00',
            ],
            [
                'category' => 'electronics',
                'sku' => 'ELEC-HEADPHONES-01',
                'name' => 'Wireless Headphones',
                'description' => 'Noise-isolating wireless headphones for focused work.',
                'selling_price' => '1800000.00',
                'production_cost' => '1100000.00',
            ],
            [
                'category' => 'home-office',
                'sku' => 'OFFICE-DESK-01',
                'name' => 'Adjustable Standing Desk',
                'description' => 'Height-adjustable desk for flexible office setups.',
                'selling_price' => '5500000.00',
                'production_cost' => '3900000.00',
            ],
            [
                'category' => 'home-office',
                'sku' => 'OFFICE-CHAIR-01',
                'name' => 'Ergonomic Office Chair',
                'description' => 'Supportive ergonomic chair for extended work sessions.',
                'selling_price' => '3200000.00',
                'production_cost' => '2200000.00',
            ],
            [
                'category' => 'lifestyle',
                'sku' => 'LIFE-BACKPACK-01',
                'name' => 'Travel Backpack',
                'description' => 'Durable carry-on backpack with organized compartments.',
                'selling_price' => '1200000.00',
                'production_cost' => '650000.00',
            ],
            [
                'category' => 'lifestyle',
                'sku' => 'LIFE-BOTTLE-01',
                'name' => 'Smart Water Bottle',
                'description' => 'Insulated bottle with hydration reminders.',
                'selling_price' => '650000.00',
                'production_cost' => '280000.00',
            ],
        ];
    }

    /**
     * @return list<array{
     *     name: string,
     *     email: string,
     *     phone: string|null,
     *     address: string|null
     * }>
     */
    private function customers(): array
    {
        return [
            [
                'name' => 'PT Nusantara Retail',
                'email' => 'purchasing@nusantara-retail.test',
                'phone' => '+62215550101',
                'address' => 'Jakarta',
            ],
            [
                'name' => 'CV Mitra Digital',
                'email' => 'operations@mitra-digital.test',
                'phone' => '+62225550102',
                'address' => 'Bandung',
            ],
            [
                'name' => 'PT Solusi Kerja',
                'email' => 'finance@solusi-kerja.test',
                'phone' => null,
                'address' => 'Surabaya',
            ],
            [
                'name' => 'Koperasi Maju Bersama',
                'email' => 'admin@maju-bersama.test',
                'phone' => '+622745550104',
                'address' => null,
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     name: string,
     *     email: string,
     *     phone: string
     * }>
     */
    private function investors(): array
    {
        return [
            'aria' => [
                'name' => 'Aria Pratama',
                'email' => 'aria.investor@example.com',
                'phone' => '+6281200001001',
            ],
            'bima' => [
                'name' => 'Bima Santoso',
                'email' => 'bima.investor@example.com',
                'phone' => '+6281200001002',
            ],
            'citra' => [
                'name' => 'Citra Lestari',
                'email' => 'citra.investor@example.com',
                'phone' => '+6281200001003',
            ],
        ];
    }
}
