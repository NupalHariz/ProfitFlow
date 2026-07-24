<?php

namespace Tests\Feature\Http\ProfitRecipients;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

class ProfitRecipientAccessTest extends ProfitRecipientHttpTestCase
{
    public function test_guests_cannot_access_any_profit_recipient_route(): void
    {
        $recipient = $this->recipient();

        foreach (
            $this->routeRequests($recipient->id) as [$method, $uri, $data]
        ) {
            $this->call($method, $uri, $data)
                ->assertRedirect(route('login'));
        }
    }

    public function test_unverified_users_cannot_access_any_profit_recipient_route(): void
    {
        $user = User::factory()->unverified()->create();
        $recipient = $this->recipient();

        foreach (
            $this->routeRequests($recipient->id) as [$method, $uri, $data]
        ) {
            $this->actingAs($user)
                ->call($method, $uri, $data)
                ->assertRedirect(route('verification.notice'));
        }
    }

    public function test_verified_user_can_view_all_profit_recipient_pages(): void
    {
        $user = $this->user();
        $recipient = $this->recipient();
        $pages = [
            [
                route('profit-recipients.index'),
                'profit-recipients/index',
            ],
            [
                route('profit-recipients.create'),
                'profit-recipients/create',
            ],
            [
                route('profit-recipients.show', $recipient),
                'profit-recipients/show',
            ],
            [
                route('profit-recipients.edit', $recipient),
                'profit-recipients/edit',
            ],
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

    public function test_exactly_seven_routes_exist_without_delete_restore_or_force_delete(): void
    {
        $recipientRoutes = collect(
            Route::getRoutes()->getRoutesByName()
        )->keys()->filter(
            fn (string $name): bool => str_starts_with(
                $name,
                'profit-recipients.'
            )
        );

        $this->assertCount(7, $recipientRoutes);
        $this->assertEqualsCanonicalizing([
            'profit-recipients.index',
            'profit-recipients.create',
            'profit-recipients.store',
            'profit-recipients.show',
            'profit-recipients.edit',
            'profit-recipients.update',
            'profit-recipients.update-status',
        ], $recipientRoutes->values()->all());
        $this->assertFalse(Route::has('profit-recipients.destroy'));
        $this->assertFalse(Route::has('profit-recipients.delete'));
        $this->assertFalse(Route::has('profit-recipients.restore'));
        $this->assertFalse(Route::has('profit-recipients.force-delete'));
    }

    /**
     * @return list<array{string, string, array<string, mixed>}>
     */
    private function routeRequests(int $recipientId): array
    {
        $investor = $this->investor();

        return [
            ['GET', route('profit-recipients.index'), []],
            ['GET', route('profit-recipients.create'), []],
            [
                'POST',
                route('profit-recipients.store'),
                $this->recipientPayload($investor),
            ],
            [
                'GET',
                route('profit-recipients.show', $recipientId),
                [],
            ],
            [
                'GET',
                route('profit-recipients.edit', $recipientId),
                [],
            ],
            [
                'PATCH',
                route('profit-recipients.update', $recipientId),
                $this->updatePayload(),
            ],
            [
                'PATCH',
                route('profit-recipients.update-status', $recipientId),
                ['is_active' => false],
            ],
        ];
    }
}
