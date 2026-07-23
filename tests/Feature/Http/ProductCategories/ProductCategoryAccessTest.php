<?php

namespace Tests\Feature\Http\ProductCategories;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

class ProductCategoryAccessTest extends ProductCategoryHttpTestCase
{
    public function test_guests_cannot_access_any_category_management_route(): void
    {
        $category = $this->category();

        foreach ($this->routeRequests($category->id) as [$method, $uri]) {
            $this->call($method, $uri, $this->categoryPayload())
                ->assertRedirect(route('login'));
        }
    }

    public function test_unverified_users_cannot_access_any_category_management_route(): void
    {
        $user = User::factory()->unverified()->create();
        $category = $this->category();

        foreach ($this->routeRequests($category->id) as [$method, $uri]) {
            $this->actingAs($user)
                ->call($method, $uri, $this->categoryPayload())
                ->assertRedirect(route('verification.notice'));
        }
    }

    public function test_verified_user_can_view_index_create_and_edit_pages(): void
    {
        $user = $this->user();
        $category = $this->category();

        $this->actingAs($user)
            ->get(route('product-categories.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('product-categories/index')
            );
        $this->actingAs($user)
            ->get(route('product-categories.create'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('product-categories/create')
            );
        $this->actingAs($user)
            ->get(route('product-categories.edit', $category))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('product-categories/edit')
                    ->where('category.id', $category->id)
            );
    }

    public function test_soft_deleted_category_binding_returns_not_found_for_every_bound_route(): void
    {
        $user = $this->user();
        $category = $this->category();
        $category->delete();
        $boundRequests = [
            ['GET', route('product-categories.edit', $category->id)],
            ['PATCH', route('product-categories.update', $category->id)],
            [
                'PATCH',
                route('product-categories.update-status', $category->id),
            ],
            ['DELETE', route('product-categories.destroy', $category->id)],
        ];

        foreach ($boundRequests as [$method, $uri]) {
            $this->actingAs($user)
                ->call($method, $uri, $this->categoryPayload())
                ->assertNotFound();
        }
    }

    /**
     * @return list<array{string, string}>
     */
    private function routeRequests(int $categoryId): array
    {
        return [
            ['GET', route('product-categories.index')],
            ['GET', route('product-categories.create')],
            ['POST', route('product-categories.store')],
            ['GET', route('product-categories.edit', $categoryId)],
            ['PATCH', route('product-categories.update', $categoryId)],
            [
                'PATCH',
                route('product-categories.update-status', $categoryId),
            ],
            ['DELETE', route('product-categories.destroy', $categoryId)],
        ];
    }
}
