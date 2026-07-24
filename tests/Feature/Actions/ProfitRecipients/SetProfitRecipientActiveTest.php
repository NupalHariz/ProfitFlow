<?php

namespace Tests\Feature\Actions\ProfitRecipients;

use App\Actions\ProfitRecipients\SetProfitRecipientActive;
use App\Exceptions\ProfitRecipients\InvalidProfitRecipient;
use App\Models\ProfitDistribution;
use App\Models\ProfitSchemeAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SetProfitRecipientActiveTest extends ProfitRecipientActionTestCase
{
    public function test_status_changes_only_recipient_and_preserves_investor_and_usage(): void
    {
        $investor = $this->investor(['is_active' => true]);
        $recipient = $this->createManagedRecipient([
            'investor_id' => $investor->id,
        ]);
        $allocation = ProfitSchemeAllocation::factory()
            ->for($recipient, 'recipient')
            ->create();
        $distribution = ProfitDistribution::factory()
            ->for($recipient, 'recipient')
            ->create();
        $investorState = DB::table('investors')
            ->where('id', $investor->id)
            ->first();
        $allocationState = $allocation->getAttributes();
        $distributionState = $distribution->getAttributes();

        $inactive = app(SetProfitRecipientActive::class)->execute(
            $recipient,
            ['is_active' => false]
        );
        $active = app(SetProfitRecipientActive::class)->execute(
            $inactive,
            ['is_active' => true]
        );

        $this->assertFalse($inactive->is_active);
        $this->assertTrue($active->is_active);
        $this->assertEquals(
            $investorState,
            DB::table('investors')
                ->where('id', $investor->id)
                ->first()
        );
        $this->assertEquals(
            $allocationState,
            $allocation->fresh()?->getAttributes()
        );
        $this->assertEquals(
            $distributionState,
            $distribution->fresh()?->getAttributes()
        );
    }

    public function test_repeated_status_request_is_a_locked_no_op(): void
    {
        $recipient = $this->createManagedRecipient();
        $timestamp = CarbonImmutable::parse('2026-01-01 00:00:00');
        DB::table('profit_recipients')
            ->where('id', $recipient->id)
            ->update(['updated_at' => $timestamp]);
        $recipient->refresh();
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(SetProfitRecipientActive::class)->execute(
            $recipient,
            ['is_active' => true]
        );
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertTrue(
            $recipient->fresh()?->updated_at?->equalTo($timestamp)
        );
        $this->assertTrue(collect($queries)->contains(
            fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "profit_recipients"'
            ) && str_contains(strtolower($query['query']), 'for update')
        ));
    }

    public function test_status_action_rejects_non_boolean_or_unrelated_input(): void
    {
        $recipient = $this->createManagedRecipient();

        foreach ([
            ['is_active' => '1'],
            ['is_active' => false, 'code' => 'NO'],
            [],
        ] as $input) {
            try {
                app(SetProfitRecipientActive::class)->execute(
                    $recipient,
                    $input
                );
                $this->fail('Expected status input rejection.');
            } catch (InvalidProfitRecipient) {
                $this->assertTrue($recipient->fresh()?->is_active);
            }
        }
    }
}
