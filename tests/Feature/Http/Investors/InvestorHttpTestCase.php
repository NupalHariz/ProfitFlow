<?php

namespace Tests\Feature\Http\Investors;

use App\Models\Investor;
use App\Models\ProfitRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class InvestorHttpTestCase extends TestCase
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
    protected function investor(array $attributes = []): Investor
    {
        return Investor::factory()->create($attributes);
    }

    protected function recipient(
        Investor $investor,
        array $attributes = []
    ): ProfitRecipient {
        return ProfitRecipient::factory()
            ->for($investor)
            ->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function investorPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Naufal Pratama',
            'email' => 'naufal@example.test',
            'phone' => '+6281234567890',
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function investorUpdatePayload(
        array $overrides = []
    ): array {
        return array_replace([
            'name' => 'Naufal Pratama',
            'email' => 'naufal@example.test',
            'phone' => '+6281234567890',
        ], $overrides);
    }
}
