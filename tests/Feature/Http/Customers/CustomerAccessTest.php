<?php

namespace Tests\Feature\Http\Customers;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

class CustomerAccessTest extends CustomerHttpTestCase
{
    public function test_guests_cannot_access_any_customer_management_route(): void
    {
        $customer = $this->customer();

        foreach (
            $this->routeRequests(
                $customer->id,
                $this->customerPayload()
            ) as [$method, $uri, $data]
        ) {
            $this->call($method, $uri, $data)
                ->assertRedirect(route('login'));
        }
    }

    public function test_unverified_users_cannot_access_any_customer_management_route(): void
    {
        $user = User::factory()->unverified()->create();
        $customer = $this->customer();

        foreach (
            $this->routeRequests(
                $customer->id,
                $this->customerPayload()
            ) as [$method, $uri, $data]
        ) {
            $this->actingAs($user)
                ->call($method, $uri, $data)
                ->assertRedirect(route('verification.notice'));
        }
    }

    public function test_verified_user_can_view_all_customer_pages(): void
    {
        $user = $this->user();
        $customer = $this->customer();

        $pages = [
            [route('customers.index'), 'customers/index'],
            [route('customers.create'), 'customers/create'],
            [route('customers.show', $customer), 'customers/show'],
            [route('customers.edit', $customer), 'customers/edit'],
        ];

        foreach ($pages as [$uri, $component]) {
            $this->actingAs($user)
                ->get($uri)
                ->assertOk()
                ->assertInertia(
                    fn (Assert $page) => $page->component($component)
                );
        }
    }

    public function test_soft_deleted_customer_binding_is_not_found_on_bound_routes(): void
    {
        $user = $this->user();
        $customer = $this->customer();
        $customer->delete();
        $requests = [
            ['GET', route('customers.show', $customer->id), []],
            ['GET', route('customers.edit', $customer->id), []],
            [
                'PATCH',
                route('customers.update', $customer->id),
                $this->customerPayload(),
            ],
            ['DELETE', route('customers.destroy', $customer->id), []],
        ];

        foreach ($requests as [$method, $uri, $data]) {
            $this->actingAs($user)
                ->call($method, $uri, $data)
                ->assertNotFound();
        }
    }

    public function test_restore_and_force_delete_routes_do_not_exist(): void
    {
        $this->assertFalse(Route::has('customers.restore'));
        $this->assertFalse(Route::has('customers.force-destroy'));
        $this->assertFalse(Route::has('customers.force-delete'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{string, string, array<string, mixed>}>
     */
    private function routeRequests(
        int $customerId,
        array $payload
    ): array {
        return [
            ['GET', route('customers.index'), []],
            ['GET', route('customers.create'), []],
            ['POST', route('customers.store'), $payload],
            ['GET', route('customers.show', $customerId), []],
            ['GET', route('customers.edit', $customerId), []],
            ['PATCH', route('customers.update', $customerId), $payload],
            ['DELETE', route('customers.destroy', $customerId), []],
        ];
    }
}
