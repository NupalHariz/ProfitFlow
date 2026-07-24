<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InternalAdministratorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_internal_business_route_requires_authentication_and_verification(): void
    {
        $expectedRoutes = $this->internalRouteMethods();
        $actualRouteNames = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn (IlluminateRoute $route) => $route->getName())
            ->filter(fn (?string $routeName) => $routeName !== null
                && $this->isInternalRouteName($routeName))
            ->sort()
            ->values()
            ->all();
        $expectedRouteNames = array_keys($expectedRoutes);
        sort($expectedRouteNames);

        $this->assertSame($expectedRouteNames, $actualRouteNames);

        foreach ($expectedRoutes as $routeName => $expectedMethods) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertInstanceOf(
                IlluminateRoute::class,
                $route,
                "Missing internal route [{$routeName}].",
            );
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('verified', $route->gatherMiddleware());
            $this->assertSame($expectedMethods, $route->methods());
        }
    }

    public function test_user_model_and_verification_support_preserve_the_unverified_flow(): void
    {
        $this->assertTrue(
            is_a(User::class, MustVerifyEmail::class, allow_string: true),
        );

        foreach ([
            'verification.notice',
            'verification.send',
            'verification.verify',
            'logout',
        ] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertInstanceOf(IlluminateRoute::class, $route);
            $this->assertNotContains('verified', $route->gatherMiddleware());
        }

        Notification::fake();

        $unverifiedUser = User::factory()->unverified()->create();

        $this->actingAs($unverifiedUser)
            ->get(route('verification.notice'))
            ->assertOk();

        $this->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($unverifiedUser, VerifyEmail::class);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_guest_unverified_and_verified_access_matches_the_internal_model(): void
    {
        $unverifiedUser = User::factory()->unverified()->create();
        $verifiedUser = User::factory()->create();

        foreach ($this->internalIndexRoutes() as $routeName) {
            $this->get(route($routeName, absolute: false))
                ->assertRedirect(route('login'));
        }

        foreach ($this->internalIndexRoutes() as $routeName) {
            $this->actingAs($unverifiedUser)
                ->get(route($routeName, absolute: false))
                ->assertRedirect(route('verification.notice'));
        }

        foreach ($this->internalIndexRoutes() as $routeName) {
            $this->actingAs($verifiedUser)
                ->get(route($routeName, absolute: false))
                ->assertOk();
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function internalRouteMethods(): array
    {
        return [
            'dashboard' => ['GET', 'HEAD'],
            'product-categories.index' => ['GET', 'HEAD'],
            'product-categories.create' => ['GET', 'HEAD'],
            'product-categories.store' => ['POST'],
            'product-categories.edit' => ['GET', 'HEAD'],
            'product-categories.update-status' => ['PATCH'],
            'product-categories.update' => ['PATCH'],
            'product-categories.destroy' => ['DELETE'],
            'products.index' => ['GET', 'HEAD'],
            'products.create' => ['GET', 'HEAD'],
            'products.store' => ['POST'],
            'products.edit' => ['GET', 'HEAD'],
            'products.update-status' => ['PATCH'],
            'products.update' => ['PATCH'],
            'products.destroy' => ['DELETE'],
            'customers.index' => ['GET', 'HEAD'],
            'customers.create' => ['GET', 'HEAD'],
            'customers.store' => ['POST'],
            'customers.edit' => ['GET', 'HEAD'],
            'customers.update' => ['PATCH'],
            'customers.destroy' => ['DELETE'],
            'customers.show' => ['GET', 'HEAD'],
            'investors.index' => ['GET', 'HEAD'],
            'investors.create' => ['GET', 'HEAD'],
            'investors.store' => ['POST'],
            'investors.edit' => ['GET', 'HEAD'],
            'investors.update-status' => ['PATCH'],
            'investors.update' => ['PATCH'],
            'investors.destroy' => ['DELETE'],
            'investors.show' => ['GET', 'HEAD'],
            'profit-recipients.index' => ['GET', 'HEAD'],
            'profit-recipients.create' => ['GET', 'HEAD'],
            'profit-recipients.store' => ['POST'],
            'profit-recipients.edit' => ['GET', 'HEAD'],
            'profit-recipients.update-status' => ['PATCH'],
            'profit-recipients.update' => ['PATCH'],
            'profit-recipients.show' => ['GET', 'HEAD'],
            'profit-schemes.index' => ['GET', 'HEAD'],
            'profit-schemes.create' => ['GET', 'HEAD'],
            'profit-schemes.store' => ['POST'],
            'profit-schemes.show' => ['GET', 'HEAD'],
            'profit-schemes.versions.create' => ['GET', 'HEAD'],
            'profit-schemes.versions.store' => ['POST'],
            'profit-schemes.versions.edit' => ['GET', 'HEAD'],
            'profit-schemes.versions.update' => ['PATCH'],
            'profit-schemes.versions.allocations.update' => ['PUT'],
            'profit-schemes.versions.publish' => ['POST'],
            'transactions.index' => ['GET', 'HEAD'],
            'transactions.create' => ['GET', 'HEAD'],
            'transactions.store' => ['POST'],
            'transactions.edit' => ['GET', 'HEAD'],
            'transactions.update' => ['PATCH'],
            'transactions.complete' => ['POST'],
            'transactions.cancel' => ['POST'],
            'transactions.retry-distribution' => ['POST'],
            'transactions.show' => ['GET', 'HEAD'],
        ];
    }

    /**
     * @return list<string>
     */
    private function internalIndexRoutes(): array
    {
        return [
            'dashboard',
            'product-categories.index',
            'products.index',
            'customers.index',
            'investors.index',
            'profit-recipients.index',
            'profit-schemes.index',
            'transactions.index',
        ];
    }

    private function isInternalRouteName(string $routeName): bool
    {
        return $routeName === 'dashboard'
            || str_starts_with($routeName, 'product-categories.')
            || str_starts_with($routeName, 'products.')
            || str_starts_with($routeName, 'customers.')
            || str_starts_with($routeName, 'investors.')
            || str_starts_with($routeName, 'profit-recipients.')
            || str_starts_with($routeName, 'profit-schemes.')
            || str_starts_with($routeName, 'transactions.');
    }
}
