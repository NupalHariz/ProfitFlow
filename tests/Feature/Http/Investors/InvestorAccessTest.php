<?php

namespace Tests\Feature\Http\Investors;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

class InvestorAccessTest extends InvestorHttpTestCase
{
    public function test_guests_cannot_access_any_investor_management_route(): void
    {
        $investor = $this->investor();

        foreach (
            $this->routeRequests($investor->id) as [$method, $uri, $data]
        ) {
            $this->call($method, $uri, $data)
                ->assertRedirect(route('login'));
        }
    }

    public function test_unverified_users_cannot_access_any_investor_management_route(): void
    {
        $user = User::factory()->unverified()->create();
        $investor = $this->investor();

        foreach (
            $this->routeRequests($investor->id) as [$method, $uri, $data]
        ) {
            $this->actingAs($user)
                ->call($method, $uri, $data)
                ->assertRedirect(route('verification.notice'));
        }
    }

    public function test_verified_user_can_view_all_investor_pages(): void
    {
        $user = $this->user();
        $investor = $this->investor();
        $pages = [
            [route('investors.index'), 'investors/index'],
            [route('investors.create'), 'investors/create'],
            [route('investors.show', $investor), 'investors/show'],
            [route('investors.edit', $investor), 'investors/edit'],
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

    public function test_soft_deleted_investor_binding_is_not_found_on_bound_routes(): void
    {
        $user = $this->user();
        $investor = $this->investor();
        $investor->delete();
        $requests = [
            ['GET', route('investors.show', $investor->id), []],
            ['GET', route('investors.edit', $investor->id), []],
            [
                'PATCH',
                route('investors.update', $investor->id),
                $this->investorUpdatePayload(),
            ],
            [
                'PATCH',
                route('investors.update-status', $investor->id),
                ['is_active' => false],
            ],
            ['DELETE', route('investors.destroy', $investor->id), []],
        ];

        foreach ($requests as [$method, $uri, $data]) {
            $this->actingAs($user)
                ->call($method, $uri, $data)
                ->assertNotFound();
        }
    }

    public function test_restore_and_force_delete_routes_do_not_exist(): void
    {
        $this->assertFalse(Route::has('investors.restore'));
        $this->assertFalse(Route::has('investors.force-destroy'));
        $this->assertFalse(Route::has('investors.force-delete'));
    }

    /**
     * @return list<array{string, string, array<string, mixed>}>
     */
    private function routeRequests(int $investorId): array
    {
        return [
            ['GET', route('investors.index'), []],
            ['GET', route('investors.create'), []],
            ['POST', route('investors.store'), $this->investorPayload()],
            ['GET', route('investors.show', $investorId), []],
            ['GET', route('investors.edit', $investorId), []],
            [
                'PATCH',
                route('investors.update', $investorId),
                $this->investorUpdatePayload(),
            ],
            [
                'PATCH',
                route('investors.update-status', $investorId),
                ['is_active' => false],
            ],
            ['DELETE', route('investors.destroy', $investorId), []],
        ];
    }
}
