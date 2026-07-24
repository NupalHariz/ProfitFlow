<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $previousTestNow = CarbonImmutable::getTestNow();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-07-24 12:00:00', 'UTC')
        );

        try {
            $this->call([
                MasterDataSeeder::class,
                ProfitSchemeDemoSeeder::class,
                TransactionDemoSeeder::class,
            ]);
        } finally {
            CarbonImmutable::setTestNow($previousTestNow);
        }
    }
}
