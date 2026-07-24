<?php

namespace Tests\Feature\Actions\ProfitRecipients;

use App\Actions\ProfitRecipients\CreateProfitRecipient;
use App\Enums\ProfitRecipientType;
use App\Models\Investor;
use App\Models\ProfitRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ProfitRecipientActionTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', config('database.default'));
    }

    protected function investor(
        array $attributes = []
    ): Investor {
        return Investor::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createManagedRecipient(
        array $overrides = []
    ): ProfitRecipient {
        $investor = $overrides['investor_id'] ?? null;

        if ($investor === null
            && ($overrides['type'] ?? null)
                !== ProfitRecipientType::Company
            && ($overrides['type'] ?? null) !== 'company') {
            $investor = $this->investor()->id;
        }

        return app(CreateProfitRecipient::class)->execute(
            $this->recipientData([
                ...$overrides,
                'investor_id' => $investor,
            ])
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function recipientData(
        array $overrides = []
    ): array {
        return array_replace([
            'code' => 'RECIPIENT-NAUFAL',
            'type' => ProfitRecipientType::Investor,
            'investor_id' => null,
            'display_name' => 'Naufal Allocation Identity',
            'is_active' => true,
        ], $overrides);
    }
}
