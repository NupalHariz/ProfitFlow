<?php

namespace Tests\Feature\Http\Customers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class CustomerHttpTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', config('database.default'));
    }

    protected function user(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function customer(array $attributes = []): Customer
    {
        return Customer::factory()->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function customerPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'PT Nusantara Retail',
            'email' => 'finance@nusantara.test',
            'phone' => '+62 21 555 0101',
            'address' => 'Jakarta',
        ], $overrides);
    }
}
