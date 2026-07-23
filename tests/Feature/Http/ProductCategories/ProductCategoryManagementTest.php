<?php

namespace Tests\Feature\Http\ProductCategories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProfitSchemeVersion;

class ProductCategoryManagementTest extends ProductCategoryHttpTestCase
{
    public function test_verified_user_can_create_normalized_category_with_server_slug(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)
            ->post(route('product-categories.store'), [
                'name' => '  Home & Office  ',
                'description' => '  Everyday products.  ',
                'is_active' => false,
            ]);
        $category = ProductCategory::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('product-categories.edit', $category)
            );
        $this->assertSame('Home & Office', $category->name);
        $this->assertSame('home-office', $category->slug);
        $this->assertSame('Everyday products.', $category->description);
        $this->assertFalse($category->is_active);
    }

    public function test_store_validates_required_trimmed_and_maximum_name(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('product-categories.store'), [
                'name' => '   ',
                'description' => null,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('name');
        $this->actingAs($user)
            ->post(route('product-categories.store'), [
                'name' => str_repeat('a', 121),
                'description' => null,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('product_categories', 0);
    }

    public function test_description_is_optional_and_blank_is_normalized_to_null(): void
    {
        $this->actingAs($this->user())
            ->post(
                route('product-categories.store'),
                $this->categoryPayload(['description' => '   '])
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_categories', [
            'name' => 'Home & Office',
            'description' => null,
        ]);
    }

    public function test_client_supplied_slug_and_protected_fields_are_rejected(): void
    {
        $this->actingAs($this->user())
            ->post(
                route('product-categories.store'),
                $this->categoryPayload([
                    'slug' => 'client-slug',
                    'products_count' => 100,
                ])
            )
            ->assertSessionHasErrors(['slug', 'products_count']);

        $this->assertDatabaseCount('product_categories', 0);
    }

    public function test_repeated_http_creation_uses_deterministic_slug_suffix(): void
    {
        $user = $this->user();

        foreach (range(1, 3) as $_) {
            $this->actingAs($user)
                ->post(
                    route('product-categories.store'),
                    $this->categoryPayload()
                )
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(
            ['home-office', 'home-office-2', 'home-office-3'],
            ProductCategory::query()->orderBy('id')->pluck('slug')->all()
        );
    }

    public function test_general_update_changes_only_name_and_description(): void
    {
        $category = $this->category([
            'name' => 'Old Name',
            'slug' => 'stable-slug',
            'description' => 'Old description',
            'is_active' => false,
        ]);

        $this->actingAs($this->user())
            ->patch(route('product-categories.update', $category), [
                'name' => '  New Name  ',
                'description' => '  New description  ',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('product-categories.edit', $category)
            );
        $fresh = $category->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame('New Name', $fresh->name);
        $this->assertSame('New description', $fresh->description);
        $this->assertSame('stable-slug', $fresh->slug);
        $this->assertFalse($fresh->is_active);
    }

    public function test_general_update_rejects_status_and_slug_changes(): void
    {
        $category = $this->category([
            'slug' => 'stable-slug',
            'is_active' => true,
        ]);

        $this->actingAs($this->user())
            ->patch(route('product-categories.update', $category), [
                'name' => 'Changed',
                'description' => null,
                'slug' => 'not-stable',
                'is_active' => false,
            ])
            ->assertSessionHasErrors(['slug', 'is_active']);
        $fresh = $category->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame('stable-slug', $fresh->slug);
        $this->assertTrue($fresh->is_active);
    }

    public function test_status_endpoint_deactivates_and_reactivates_without_changing_products(): void
    {
        $category = $this->category(['is_active' => true]);
        $product = Product::factory()
            ->for($category, 'category')
            ->create(['is_active' => true]);
        $user = $this->user();

        $this->actingAs($user)
            ->patch(
                route('product-categories.update-status', $category),
                ['is_active' => false]
            )
            ->assertSessionHasNoErrors();
        $this->assertFalse($category->fresh()?->is_active);
        $this->assertTrue($product->fresh()?->is_active);

        $this->actingAs($user)
            ->patch(
                route('product-categories.update-status', $category),
                ['is_active' => true]
            )
            ->assertSessionHasNoErrors();
        $this->assertTrue($category->fresh()?->is_active);
        $this->assertTrue($product->fresh()?->is_active);
    }

    public function test_status_endpoint_rejects_unrelated_fields(): void
    {
        $category = $this->category();

        $this->actingAs($this->user())
            ->patch(
                route('product-categories.update-status', $category),
                [
                    'is_active' => false,
                    'name' => 'Not allowed',
                ]
            )
            ->assertSessionHasErrors('name');

        $this->assertTrue($category->fresh()?->is_active);
    }

    public function test_unreferenced_category_can_be_soft_deleted_over_http(): void
    {
        $category = $this->category();

        $this->actingAs($this->user())
            ->delete(route('product-categories.destroy', $category))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('product-categories.index'));

        $this->assertSoftDeleted('product_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_product_reference_delete_failure_is_visible_and_non_destructive(): void
    {
        $category = $this->category();
        $product = Product::factory()
            ->for($category, 'category')
            ->create();

        $this->actingAs($this->user())
            ->from(route('product-categories.edit', $category))
            ->delete(route('product-categories.destroy', $category))
            ->assertRedirect(route('product-categories.edit', $category))
            ->assertSessionHasErrors('category');

        $this->assertNull($category->fresh()?->deleted_at);
        $this->assertNotNull($product->fresh());
    }

    public function test_profit_scheme_version_delete_failure_is_visible_and_non_destructive(): void
    {
        $category = $this->category();
        $version = ProfitSchemeVersion::factory()
            ->forCategory($category)
            ->create();

        $this->actingAs($this->user())
            ->delete(route('product-categories.destroy', $category))
            ->assertSessionHasErrors('category');

        $this->assertNull($category->fresh()?->deleted_at);
        $this->assertNotNull($version->fresh());
    }
}
