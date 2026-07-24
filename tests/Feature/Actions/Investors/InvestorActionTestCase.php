<?php

namespace Tests\Feature\Actions\Investors;

use App\Actions\Investors\CreateInvestor;
use App\Models\Investor;
use App\Models\ProfitRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class InvestorActionTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', config('database.default'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createManagedInvestor(
        array $overrides = []
    ): Investor {
        return app(CreateInvestor::class)->execute(
            $this->investorData($overrides)
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function investorData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Naufal Pratama',
            'email' => 'naufal@example.test',
            'phone' => '+6281234567890',
            'is_active' => true,
        ], $overrides);
    }

    protected function recipient(
        Investor $investor,
        array $overrides = []
    ): ProfitRecipient {
        return ProfitRecipient::factory()
            ->for($investor)
            ->create($overrides);
    }
}
