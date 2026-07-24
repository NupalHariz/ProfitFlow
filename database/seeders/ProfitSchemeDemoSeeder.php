<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Models\ProfitRecipient;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ProfitSchemeDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::query()
                ->where('email', 'admin@example.com')
                ->sole();
            $electronics = ProductCategory::query()
                ->where('slug', 'electronics')
                ->sole();
            $homeOffice = ProductCategory::query()
                ->where('slug', 'home-office')
                ->sole();
            $lifestyle = ProductCategory::query()
                ->where('slug', 'lifestyle')
                ->sole();

            $electronicsGrowth = $this->scheme(
                'electronics-growth',
                'Electronics Growth',
                'Category-specific profit sharing for Electronics products.',
                $admin
            );
            $homeOfficeGrowth = $this->scheme(
                'home-office-growth',
                'Home & Office Growth',
                'Category-specific profit sharing for Home & Office products.',
                $admin
            );
            $lifestyleGrowth = $this->scheme(
                'lifestyle-growth',
                'Lifestyle Growth',
                'Category-specific profit sharing for Lifestyle products.',
                $admin
            );

            $this->publishedVersion(
                scheme: $electronicsGrowth,
                versionNumber: 1,
                category: $electronics,
                effectiveFrom: '2026-07-01',
                effectiveUntil: '2026-07-14',
                priority: 100,
                publishedAt: '2026-06-30 09:00:00',
                admin: $admin,
                allocations: [
                    'investor-aria' => '50.0000',
                    'investor-citra' => '20.0000',
                    'company' => '30.0000',
                ]
            );
            $this->publishedVersion(
                scheme: $electronicsGrowth,
                versionNumber: 2,
                category: $electronics,
                effectiveFrom: '2026-07-15',
                effectiveUntil: null,
                priority: 100,
                publishedAt: '2026-07-14 09:00:00',
                admin: $admin,
                allocations: [
                    'investor-aria' => '40.0000',
                    'investor-bima' => '30.0000',
                    'company' => '30.0000',
                ]
            );
            $this->publishedVersion(
                scheme: $homeOfficeGrowth,
                versionNumber: 1,
                category: $homeOffice,
                effectiveFrom: '2026-07-01',
                effectiveUntil: null,
                priority: 100,
                publishedAt: '2026-06-30 09:05:00',
                admin: $admin,
                allocations: [
                    'investor-aria' => '50.0000',
                    'investor-bima' => '50.0000',
                ]
            );
            $this->publishedVersion(
                scheme: $lifestyleGrowth,
                versionNumber: 1,
                category: $lifestyle,
                effectiveFrom: '2026-07-01',
                effectiveUntil: null,
                priority: 10,
                publishedAt: '2026-06-30 09:10:00',
                admin: $admin,
                allocations: [
                    'investor-aria' => '40.0000',
                    'investor-citra' => '30.0000',
                    'company' => '30.0000',
                ]
            );
        });
    }

    private function scheme(
        string $code,
        string $name,
        string $description,
        User $admin
    ): ProfitScheme {
        return ProfitScheme::query()->updateOrCreate([
            'code' => $code,
        ], [
            'name' => $name,
            'description' => $description,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);
    }

    /**
     * @param  array<string, string>  $allocations
     */
    private function publishedVersion(
        ProfitScheme $scheme,
        int $versionNumber,
        ProductCategory $category,
        string $effectiveFrom,
        ?string $effectiveUntil,
        int $priority,
        string $publishedAt,
        User $admin,
        array $allocations
    ): ProfitSchemeVersion {
        $version = ProfitSchemeVersion::query()->firstOrCreate([
            'profit_scheme_id' => $scheme->id,
            'version' => $versionNumber,
        ], [
            'product_category_id' => $category->id,
            'minimum_profit' => '0.01',
            'maximum_profit' => null,
            'effective_from' => $effectiveFrom,
            'effective_until' => $effectiveUntil,
            'priority' => $priority,
            'is_active' => true,
            'published_at' => $publishedAt,
            'created_by_user_id' => $admin->id,
        ]);

        if (! $version->wasRecentlyCreated) {
            return $version;
        }

        $recipients = ProfitRecipient::query()
            ->whereIn('code', array_keys($allocations))
            ->get()
            ->keyBy('code');

        foreach ($allocations as $recipientCode => $percentage) {
            $recipient = $recipients->get($recipientCode);

            if (! $recipient instanceof ProfitRecipient) {
                throw new \LogicException(
                    "Demo recipient [{$recipientCode}] does not exist."
                );
            }

            ProfitSchemeAllocation::query()->create([
                'profit_scheme_version_id' => $version->id,
                'profit_recipient_id' => $recipient->id,
                'percentage' => $percentage,
            ]);
        }

        return $version;
    }
}
