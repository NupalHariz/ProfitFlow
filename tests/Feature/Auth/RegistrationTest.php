<?php

namespace Tests\Feature\Auth;

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

    public function test_guest_pages_expose_no_registration_prop_link_or_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->missing('canRegister'),
            );

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->missing('canRegister'),
            );

        $loginSource = file_get_contents(resource_path('js/pages/auth/login.tsx'));
        $welcomeSource = file_get_contents(resource_path('js/pages/welcome.tsx'));

        $this->assertIsString($loginSource);
        $this->assertIsString($welcomeSource);
        $this->assertStringNotContainsString('register()', $loginSource);
        $this->assertStringNotContainsString('Sign up', $loginSource);
        $this->assertStringNotContainsString('register()', $welcomeSource);
        $this->assertStringNotContainsString('>Register<', $welcomeSource);
        $this->assertFileDoesNotExist(resource_path('js/pages/auth/register.tsx'));
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
