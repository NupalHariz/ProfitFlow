<?php

namespace Tests\Feature\Http\ProfitRecipients;

use App\Enums\ProfitRecipientType;
use App\Models\Investor;
use App\Models\ProfitRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ProfitRecipientHttpTestCase extends TestCase
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

    protected function investor(
        array $attributes = []
    ): Investor {
        return Investor::factory()->create($attributes);
    }

    protected function recipient(
        ?Investor $investor = null,
        array $attributes = []
    ): ProfitRecipient {
        if (($attributes['type'] ?? null)
            === ProfitRecipientType::Company) {
            return ProfitRecipient::factory()
                ->company()
                ->create($attributes);
        }

        return ProfitRecipient::factory()
            ->for($investor ?? $this->investor())
            ->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function recipientPayload(
        Investor $investor,
        array $overrides = []
    ): array {
        return array_replace([
            'code' => 'RECIPIENT-NAUFAL',
            'type' => 'investor',
            'investor_id' => $investor->id,
            'display_name' => 'Naufal Allocation Identity',
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function companyPayload(
        array $overrides = []
    ): array {
        return array_replace([
            'code' => 'COMPANY-OPERATIONS',
            'type' => 'company',
            'investor_id' => null,
            'display_name' => 'Company Operations',
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function updatePayload(
        array $overrides = []
    ): array {
        return array_replace([
            'code' => 'RECIPIENT-CURRENT',
            'display_name' => 'Current Allocation Identity',
        ], $overrides);
    }
}
