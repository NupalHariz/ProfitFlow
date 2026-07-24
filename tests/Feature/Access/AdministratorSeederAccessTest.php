<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministratorSeederAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_administrator_is_repeat_safe_verified_and_can_access_the_dashboard(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()
            ->where('email', 'admin@example.com')
            ->sole();

        $this->assertSame(
            1,
            User::query()->where('email', 'admin@example.com')->count(),
        );
        $this->assertSame('ProfitFlow Administrator', $admin->name);
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue(Hash::check('password', $admin->password));

        $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard'))->assertOk();
    }
}
