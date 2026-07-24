<?php

namespace Tests\Feature\Http\Dashboard;

use App\Models\ProfitDistribution;
use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDomainRecords;
use Tests\TestCase;

abstract class DashboardTestCase extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', config('database.default'));
    }

    protected function user(): User
    {
        return User::factory()->create();
    }

    protected function distribution(
        Transaction $transaction,
        ProfitRecipient $recipient,
        string $amount,
        ?ProfitSchemeVersion $version = null
    ): ProfitDistribution {
        $version ??= $this->createSchemeVersion(
            attributes: ['published_at' => now()]
        );
        $item = $this->createTransactionItem(
            $transaction,
            version: $version,
            attributes: [
                'net_profit' => $amount,
                'rule_resolved_at' => now(),
            ]
        );

        return $this->createDistribution(
            $item,
            $version,
            $recipient,
            ['amount' => $amount]
        );
    }

    protected function additionalDistribution(
        Transaction $transaction,
        ProfitRecipient $recipient,
        string $amount,
        ProfitSchemeVersion $version
    ): ProfitDistribution {
        $item = $this->createTransactionItem(
            $transaction,
            version: $version,
            attributes: [
                'net_profit' => $amount,
                'rule_resolved_at' => now(),
            ]
        );

        return $this->createDistribution(
            $item,
            $version,
            $recipient,
            ['amount' => $amount]
        );
    }
}
