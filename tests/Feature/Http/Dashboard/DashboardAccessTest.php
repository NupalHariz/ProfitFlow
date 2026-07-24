<?php

namespace Tests\Feature\Http\Dashboard;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

class DashboardAccessTest extends DashboardTestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_access_dashboard(): void
    {
        $this->actingAs($this->user())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('dashboard')
                    ->hasAll([
                        'financialSummary',
                        'statusSummary',
                        'recentTransactions',
                        'recentFailedDistributions',
                        'recentPendingDistributions',
                        'topRecipients',
                    ])
            );
    }
}
