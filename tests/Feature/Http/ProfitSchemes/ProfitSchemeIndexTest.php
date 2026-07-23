<?php

namespace Tests\Feature\Http\ProfitSchemes;

use App\Models\ProfitScheme;
use Inertia\Testing\AssertableInertia as Assert;

class ProfitSchemeIndexTest extends ProfitSchemeHttpTestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('profit-schemes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_verified_users_can_view_index(): void
    {
        $this->actingAs($this->user())
            ->get(route('profit-schemes.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('profit-schemes/index')
                    ->has('schemes.data')
            );
    }

    public function test_index_is_paginated(): void
    {
        $user = $this->user();
        ProfitScheme::factory()
            ->count(11)
            ->for($user, 'creator')
            ->create();

        $this->actingAs($user)
            ->get(route('profit-schemes.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('schemes.data', 10)
                    ->where('schemes.meta.current_page', 1)
                    ->where('schemes.meta.last_page', 2)
                    ->where('schemes.meta.total', 11)
                    ->where(
                        'schemes.links.next',
                        fn (mixed $url): bool => is_string($url)
                            && str_contains($url, 'page=2')
                    )
            );
    }

    public function test_search_filters_by_name_and_code(): void
    {
        $user = $this->user();
        $this->scheme($user, [
            'name' => 'Electronics Partnership',
            'code' => 'electronics-main',
        ]);
        $this->scheme($user, [
            'name' => 'Fashion Partnership',
            'code' => 'fashion-special',
        ]);

        $this->actingAs($user)
            ->get(route('profit-schemes.index', [
                'search' => 'electronics',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('schemes.data', 1)
                    ->where(
                        'schemes.data.0.name',
                        'Electronics Partnership'
                    )
            );

        $this->actingAs($user)
            ->get(route('profit-schemes.index', [
                'search' => 'fashion-special',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('schemes.data', 1)
                    ->where(
                        'schemes.data.0.name',
                        'Fashion Partnership'
                    )
            );
    }

    public function test_status_filter_uses_active_state(): void
    {
        $user = $this->user();
        $this->scheme($user, [
            'name' => 'Active Scheme',
            'is_active' => true,
        ]);
        $this->scheme($user, [
            'name' => 'Inactive Scheme',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get(route('profit-schemes.index', ['status' => 'inactive']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('schemes.data', 1)
                    ->where('schemes.data.0.name', 'Inactive Scheme')
                    ->where('schemes.data.0.is_active', false)
            );
    }

    public function test_index_props_exclude_version_and_allocation_graphs(): void
    {
        $user = $this->user();
        $scheme = $this->scheme($user);
        $version = $this->draft($scheme, $user);
        $recipient = $this->recipient();
        $this->allocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);

        $this->actingAs($user)
            ->get(route('profit-schemes.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('schemes.data', 1)
                    ->where('schemes.data.0.version_count', 1)
                    ->where('schemes.data.0.maximum_version_number', 1)
                    ->missing('schemes.data.0.versions')
                    ->missing('schemes.data.0.allocations')
            );
    }
}
