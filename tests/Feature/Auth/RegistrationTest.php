<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_feature_and_routes_are_disabled(): void
    {
        $this->assertFalse(Features::enabled(Features::registration()));
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.store'));

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Unexpected User',
            'email' => 'unexpected@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', [
            'email' => 'unexpected@example.com',
        ]);
        $this->assertGuest();
    }

    public function test_login_exposes_no_registration_prop_link_or_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->missing('canRegister'),
            );

        $loginSource = file_get_contents(resource_path('js/pages/auth/login.tsx'));

        $this->assertIsString($loginSource);
        $this->assertStringNotContainsString('register()', $loginSource);
        $this->assertStringNotContainsString('Sign up', $loginSource);
        $this->assertFileDoesNotExist(resource_path('js/pages/auth/register.tsx'));
        $this->assertFileDoesNotExist(resource_path('js/pages/welcome.tsx'));
    }

    public function test_root_redirects_guests_to_login_and_users_to_dashboard(): void
    {
        $this->get(route('home'))
            ->assertRedirect(route('login', absolute: false));

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_root_does_not_bypass_email_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_required_self_service_authentication_routes_remain_available(): void
    {
        foreach ([
            'login',
            'login.store',
            'logout',
            'password.request',
            'password.email',
            'password.reset',
            'password.update',
            'verification.notice',
            'verification.verify',
            'verification.send',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Missing route [{$routeName}].");
        }
    }
}
