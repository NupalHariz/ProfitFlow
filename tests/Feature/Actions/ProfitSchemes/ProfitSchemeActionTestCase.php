<?php

namespace Tests\Feature\Actions\ProfitSchemes;

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

abstract class ProfitSchemeActionTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validVersionData(array $overrides = []): array
    {
        if (! array_key_exists('product_category_id', $overrides)) {
            $overrides['product_category_id'] = ProductCategory::factory()
                ->create()
                ->id;
        }

        return array_replace([
            'minimum_profit' => null,
            'maximum_profit' => null,
            'effective_from' => '2026-01-01',
            'effective_until' => null,
            'priority' => 10,
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createDraftVersion(
        ?ProfitScheme $scheme = null,
        ?User $creator = null,
        array $overrides = []
    ): ProfitSchemeVersion {
        $creator ??= User::factory()->create();
        $scheme ??= ProfitScheme::factory()
            ->for($creator, 'creator')
            ->create();

        return app(CreateProfitSchemeVersion::class)->execute(
            $scheme,
            $this->validVersionData($overrides),
            $creator
        );
    }

    /**
     * @param  list<array{
     *     profit_recipient_id: int,
     *     percentage: string
     * }>  $allocations
     */
    protected function replaceAllocations(
        ProfitSchemeVersion $version,
        array $allocations
    ): ProfitSchemeVersion {
        return app(ReplaceDraftProfitSchemeAllocations::class)->execute(
            $version,
            $allocations
        );
    }

    protected function publish(
        ProfitSchemeVersion $version
    ): ProfitSchemeVersion {
        return app(PublishProfitSchemeVersion::class)->execute($version);
    }

    protected function createRecipient(bool $active = true): ProfitRecipient
    {
        return ProfitRecipient::factory()->create([
            'is_active' => $active,
        ]);
    }

    protected function referenceVersion(
        ProfitSchemeVersion $version
    ): TransactionItem {
        return TransactionItem::factory()
            ->withAppliedRule($version)
            ->create();
    }
}
