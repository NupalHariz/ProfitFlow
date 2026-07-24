<?php

namespace Tests\Feature\Http\ProfitSchemes;

use App\Models\ProductCategory;
use Inertia\Testing\AssertableInertia as Assert;

class ProductCategoryAssignmentRegressionTest extends ProfitSchemeHttpTestCase
{
    public function test_crafted_version_creation_rejects_soft_deleted_category(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $category = ProductCategory::factory()->create();
        $category->delete();

        $this->actingAs($user)
            ->post(
                route('profit-schemes.versions.store', $scheme),
                $this->versionPayload([
                    'product_category_id' => $category->id,
                ])
            )
            ->assertSessionHasErrors('version');

        $this->assertDatabaseCount('profit_scheme_versions', 0);
    }

    public function test_crafted_draft_update_rejects_soft_deleted_category(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $originalCategoryId = $version->product_category_id;
        $category = ProductCategory::factory()->create();
        $category->delete();

        $this->actingAs($user)
            ->patch(route('profit-schemes.versions.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'product_category_id' => $category->id,
                'priority' => 90,
            ])
            ->assertSessionHasErrors('version');

        $fresh = $version->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(
            $originalCategoryId,
            $fresh->product_category_id
        );
        $this->assertSame(10, $fresh->priority);
    }

    public function test_inactive_non_deleted_category_remains_assignable_over_http(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $category = ProductCategory::factory()->inactive()->create();

        $this->actingAs($user)
            ->post(
                route('profit-schemes.versions.store', $scheme),
                $this->versionPayload([
                    'product_category_id' => $category->id,
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('profit_scheme_versions', [
            'profit_scheme_id' => $scheme->id,
            'product_category_id' => $category->id,
        ]);
    }

    public function test_existing_published_category_reference_remains_visible_after_soft_delete(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $category = ProductCategory::factory()->create([
            'name' => 'Historical Category',
        ]);
        $version = $this->draft($scheme, $user, [
            'product_category_id' => $category->id,
        ]);
        $recipient = $this->recipient();
        $this->allocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $published = $this->publish($version);
        $originalUpdatedAt = $published->getRawOriginal('updated_at');
        $category->delete();

        $this->actingAs($user)
            ->get(route('profit-schemes.show', $scheme))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'scheme.versions.0.product_category_id',
                        $category->id
                    )
                    ->where(
                        'scheme.versions.0.category.name',
                        'Historical Category'
                    )
                    ->where(
                        'scheme.versions.0.category.is_deleted',
                        true
                    )
            );

        $fresh = $published->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(
            $category->id,
            $fresh->product_category_id
        );
        $this->assertSame(
            $originalUpdatedAt,
            $fresh->getRawOriginal('updated_at')
        );
    }
}
