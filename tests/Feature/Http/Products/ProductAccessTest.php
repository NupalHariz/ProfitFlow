<?php

namespace Tests\Feature\Http\Products;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

class ProductAccessTest extends ProductHttpTestCase
{
    public function test_guests_cannot_access_any_product_management_route(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        foreach (
            $this->routeRequests(
                $product->id,
                $this->productPayload($category)
            ) as [$method, $uri, $data]
        ) {
            $this->call($method, $uri, $data)
                ->assertRedirect(route('login'));
        }
    }

    public function test_unverified_users_cannot_access_any_product_management_route(): void
    {
        $user = User::factory()->unverified()->create();
        $category = $this->category();
        $product = $this->product($category);

        foreach (
            $this->routeRequests(
                $product->id,
                $this->productPayload($category)
            ) as [$method, $uri, $data]
        ) {
            $this->actingAs($user)
                ->call($method, $uri, $data)
                ->assertRedirect(route('verification.notice'));
        }
    }

    public function test_verified_user_can_view_product_pages(): void
    {
        $user = $this->user();
        $product = $this->product();

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('products/index')
            );
        $this->actingAs($user)
            ->get(route('products.create'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('products/create')
            );
        $this->actingAs($user)
            ->get(route('products.edit', $product))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('products/edit')
                    ->where('product.id', $product->id)
            );
    }

    public function test_soft_deleted_product_binding_is_not_found_on_every_bound_route(): void
    {
        $user = $this->user();
        $category = $this->category();
        $product = $this->product($category);
        $product->delete();
        $requests = [
            ['GET', route('products.edit', $product->id), []],
            [
                'PATCH',
                route('products.update', $product->id),
                $this->productPayload($category),
            ],
            [
                'PATCH',
                route('products.update-status', $product->id),
                ['is_active' => false],
            ],
            ['DELETE', route('products.destroy', $product->id), []],
        ];

        foreach ($requests as [$method, $uri, $data]) {
            $this->actingAs($user)
                ->call($method, $uri, $data)
                ->assertNotFound();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{string, string, array<string, mixed>}>
     */
    private function routeRequests(
        int $productId,
        array $payload
    ): array {
        return [
            ['GET', route('products.index'), []],
            ['GET', route('products.create'), []],
            ['POST', route('products.store'), $payload],
            ['GET', route('products.edit', $productId), []],
            ['PATCH', route('products.update', $productId), $payload],
            [
                'PATCH',
                route('products.update-status', $productId),
                ['is_active' => false],
            ],
            ['DELETE', route('products.destroy', $productId), []],
        ];
    }
}
