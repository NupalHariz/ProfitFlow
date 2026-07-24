<?php

namespace Tests\Feature\Http\ProfitSchemes;

use App\Actions\ProfitSchemes\CreateProfitSchemeVersion;
use App\Actions\ProfitSchemes\PublishProfitSchemeVersion;
use App\Actions\ProfitSchemes\ReplaceDraftProfitSchemeAllocations;
use App\Models\ProductCategory;
use App\Models\ProfitRecipient;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeVersion;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ProfitSchemeHttpTestCase extends TestCase
{
    use RefreshDatabase;

    protected function user(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    protected function scheme(
        User $creator,
        array $attributes = []
    ): ProfitScheme {
        return ProfitScheme::factory()
            ->for($creator, 'creator')
            ->create($attributes);
    }

    protected function draft(
        ProfitScheme $scheme,
        User $creator,
        array $attributes = []
    ): ProfitSchemeVersion {
        if (! array_key_exists('product_category_id', $attributes)) {
            $attributes['product_category_id'] = ProductCategory::factory()
                ->create()
                ->id;
        }

        return app(CreateProfitSchemeVersion::class)->execute(
            $scheme,
            array_replace([
                'minimum_profit' => null,
                'maximum_profit' => null,
                'effective_from' => '2026-01-01',
                'effective_until' => null,
                'priority' => 10,
                'is_active' => true,
            ], $attributes),
            $creator
        );
    }

    /**
     * @param  list<array{
     *     profit_recipient_id: int,
     *     percentage: string
     * }>  $allocations
     */
    protected function allocations(
        ProfitSchemeVersion $version,
        array $allocations
    ): ProfitSchemeVersion {
        return app(
            ReplaceDraftProfitSchemeAllocations::class
        )->execute($version, $allocations);
    }

    protected function recipient(bool $active = true): ProfitRecipient
    {
        return ProfitRecipient::factory()->create([
            'is_active' => $active,
        ]);
    }

    protected function publish(
        ProfitSchemeVersion $version
    ): ProfitSchemeVersion {
        return app(PublishProfitSchemeVersion::class)->execute($version);
    }

    protected function reference(
        ProfitSchemeVersion $version
    ): TransactionItem {
        return TransactionItem::factory()
            ->withAppliedRule($version)
            ->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function versionPayload(array $overrides = []): array
    {
        if (! array_key_exists('product_category_id', $overrides)) {
            $overrides['product_category_id'] = ProductCategory::factory()
                ->create()
                ->id;
        }

        return array_replace([
            'minimum_profit' => null,
            'maximum_profit' => null,
            'effective_from' => '2026-07-01',
            'effective_until' => null,
            'priority' => 20,
            'is_active' => true,
        ], $overrides);
    }
}
