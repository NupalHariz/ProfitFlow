<?php

namespace Tests\Feature\Actions\ProfitSchemes;

use App\Actions\ProfitSchemes\UpdateDraftProfitSchemeVersion;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeVersion;
use App\Models\ProductCategory;
use App\Models\ProfitSchemeVersion;

class ProductCategoryAssignmentRegressionTest extends ProfitSchemeActionTestCase
{
    public function test_creation_rejects_soft_deleted_category(): void
    {
        $category = ProductCategory::factory()->create();
        $category->delete();

        $this->expectException(InvalidProfitSchemeVersion::class);
        $this->expectExceptionMessage('existing category');

        $this->createDraftVersion(overrides: [
            'product_category_id' => $category->id,
        ]);
    }

    public function test_draft_update_rejects_soft_deleted_category_and_preserves_rule(): void
    {
        $category = ProductCategory::factory()->create();
        $category->delete();
        $version = $this->createDraftVersion(overrides: [
            'priority' => 20,
        ]);

        try {
            app(UpdateDraftProfitSchemeVersion::class)->execute(
                $version,
                [
                    'product_category_id' => $category->id,
                    'priority' => 50,
                ]
            );
            $this->fail('Expected soft-deleted category rejection.');
        } catch (InvalidProfitSchemeVersion $exception) {
            $this->assertStringContainsString(
                'existing category',
                $exception->getMessage()
            );
        }

        $fresh = $version->fresh();

        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->product_category_id);
        $this->assertNotSame($category->id, $fresh->product_category_id);
        $this->assertSame(20, $fresh->priority);
    }

    public function test_inactive_non_deleted_category_remains_valid(): void
    {
        $category = ProductCategory::factory()->inactive()->create();

        $created = $this->createDraftVersion(overrides: [
            'product_category_id' => $category->id,
        ]);
        $updated = app(UpdateDraftProfitSchemeVersion::class)->execute(
            $created,
            [
                'product_category_id' => $category->id,
                'priority' => 44,
            ]
        );

        $this->assertSame($category->id, $updated->product_category_id);
        $this->assertSame(44, $updated->priority);
    }

    public function test_existing_published_version_is_not_rewritten_when_category_is_soft_deleted(): void
    {
        $category = ProductCategory::factory()->create();
        $version = ProfitSchemeVersion::factory()
            ->forCategory($category)
            ->published()
            ->create();
        $original = $version->getRawOriginal();

        $category->delete();
        $fresh = $version->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(
            $original['product_category_id'],
            $fresh->getRawOriginal('product_category_id')
        );
        $this->assertSame(
            $original['published_at'],
            $fresh->getRawOriginal('published_at')
        );
        $this->assertSame(
            $original['updated_at'],
            $fresh->getRawOriginal('updated_at')
        );
    }
}
