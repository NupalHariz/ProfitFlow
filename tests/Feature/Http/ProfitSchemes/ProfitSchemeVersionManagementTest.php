<?php

namespace Tests\Feature\Http\ProfitSchemes;

use App\Models\ProductCategory;
use App\Models\ProfitSchemeVersion;
use Inertia\Testing\AssertableInertia as Assert;

class ProfitSchemeVersionManagementTest extends ProfitSchemeHttpTestCase
{
    public function test_create_version_page_renders_category_lookups(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $category = ProductCategory::factory()->create([
            'name' => 'Electronics',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('profit-schemes.versions.create', $scheme))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('profit-schemes/versions/create')
                    ->where('scheme.id', $scheme->id)
                    ->has('categories', 1)
                    ->where('categories.0.id', $category->id)
                    ->where('categories.0.name', 'Electronics')
                    ->where('categories.0.is_active', true)
                    ->missing('recipients')
            );
    }

    public function test_valid_draft_version_is_created_by_authenticated_user(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);

        $this->actingAs($user)
            ->post(
                route('profit-schemes.versions.store', $scheme),
                $this->versionPayload([
                    'minimum_profit' => '10.00',
                    'maximum_profit' => '500.00',
                    'priority' => 42,
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('profit_scheme_versions', [
            'profit_scheme_id' => $scheme->id,
            'version' => 1,
            'minimum_profit' => '10.00',
            'maximum_profit' => '500.00',
            'priority' => 42,
            'published_at' => null,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_create_requires_a_category_and_rejects_null(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $missing = $this->versionPayload();
        unset($missing['product_category_id']);

        $this->actingAs($user)
            ->post(
                route('profit-schemes.versions.store', $scheme),
                $missing
            )
            ->assertSessionHasErrors('product_category_id');
        $this->actingAs($user)
            ->post(
                route('profit-schemes.versions.store', $scheme),
                $this->versionPayload(['product_category_id' => null])
            )
            ->assertSessionHasErrors('product_category_id');

        $this->assertDatabaseCount('profit_scheme_versions', 0);
    }

    public function test_new_version_redirects_to_its_edit_page(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);

        $response = $this->actingAs($user)
            ->post(
                route('profit-schemes.versions.store', $scheme),
                $this->versionPayload()
            );
        $version = ProfitSchemeVersion::query()
            ->where('profit_scheme_id', $scheme->id)
            ->firstOrFail();

        $response->assertRedirect(
            route('profit-schemes.versions.edit', [
                'scheme' => $scheme,
                'version' => $version,
            ])
        );
    }

    public function test_nested_binding_rejects_version_from_another_scheme(): void
    {
        $user = $this->user();
        $firstScheme = $this->scheme($user);
        $secondScheme = $this->scheme($user);
        $version = $this->draft($secondScheme, $user);

        $this->actingAs($user)
            ->get(route('profit-schemes.versions.edit', [
                'scheme' => $firstScheme,
                'version' => $version,
            ]))
            ->assertNotFound();
    }

    public function test_valid_draft_rule_changes_persist(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);

        $this->actingAs($user)
            ->patch(route('profit-schemes.versions.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'product_category_id' => $version
                    ->product_category_id,
                'minimum_profit' => '25.50',
                'maximum_profit' => '900.00',
                'priority' => 99,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profit-schemes.versions.edit', [
                'scheme' => $scheme,
                'version' => $version,
            ]));

        $this->assertDatabaseHas('profit_scheme_versions', [
            'id' => $version->id,
            'minimum_profit' => '25.50',
            'maximum_profit' => '900.00',
            'priority' => 99,
        ]);
    }

    public function test_published_version_update_returns_validation_error(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $recipient = $this->recipient();
        $this->allocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $this->publish($version);

        $this->actingAs($user)
            ->from(route('profit-schemes.versions.edit', [
                'scheme' => $scheme,
                'version' => $version,
            ]))
            ->patch(route('profit-schemes.versions.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'product_category_id' => $version
                    ->product_category_id,
                'priority' => 500,
            ])
            ->assertSessionHasErrors('version');

        $this->assertSame(10, $version->fresh()->priority);
    }

    public function test_historically_referenced_version_update_is_rejected(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $this->reference($version);

        $this->actingAs($user)
            ->patch(route('profit-schemes.versions.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'product_category_id' => $version
                    ->product_category_id,
                'priority' => 500,
            ])
            ->assertSessionHasErrors('version');

        $this->assertSame(10, $version->fresh()->priority);
    }

    public function test_failed_rule_update_preserves_existing_values(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user, [
            'minimum_profit' => '10.00',
            'maximum_profit' => '100.00',
            'priority' => 30,
        ]);
        $before = $version->getRawOriginal();

        $this->actingAs($user)
            ->patch(route('profit-schemes.versions.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'product_category_id' => $version
                    ->product_category_id,
                'minimum_profit' => '200.00',
                'priority' => 80,
            ])
            ->assertSessionHasErrors('version');

        $fresh = $version->fresh();

        $this->assertSame(
            $before['minimum_profit'],
            $fresh->getRawOriginal('minimum_profit')
        );
        $this->assertSame(
            $before['maximum_profit'],
            $fresh->getRawOriginal('maximum_profit')
        );
        $this->assertSame(
            $before['priority'],
            $fresh->getRawOriginal('priority')
        );
        $this->assertSame(
            $before['updated_at'],
            $fresh->getRawOriginal('updated_at')
        );
    }

    public function test_update_requires_category_and_rejects_null(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);

        $this->actingAs($user)
            ->patch(route('profit-schemes.versions.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), ['priority' => 99])
            ->assertSessionHasErrors('product_category_id');
        $this->actingAs($user)
            ->patch(route('profit-schemes.versions.update', [
                'scheme' => $scheme,
                'version' => $version,
            ]), [
                'product_category_id' => null,
                'priority' => 99,
            ])
            ->assertSessionHasErrors('product_category_id');

        $this->assertSame(10, $version->fresh()->priority);
    }
}
