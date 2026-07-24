<?php

namespace Tests\Feature\Actions\Investors;

use App\Actions\Investors\SetInvestorActive;
use App\Exceptions\Investors\InvalidInvestor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SetInvestorActiveTest extends InvestorActionTestCase
{
    public function test_investor_can_be_deactivated_and_reactivated_without_changing_recipient(): void
    {
        $investor = $this->createManagedInvestor();
        $recipient = $this->recipient($investor, [
            'display_name' => 'Independent Recipient',
            'is_active' => true,
        ]);
        $recipientState = $recipient->getAttributes();

        $inactive = app(SetInvestorActive::class)->execute(
            $investor,
            ['is_active' => false]
        );
        $active = app(SetInvestorActive::class)->execute(
            $inactive,
            ['is_active' => true]
        );

        $this->assertFalse($inactive->is_active);
        $this->assertTrue($active->is_active);
        $this->assertEquals(
            $recipientState,
            $recipient->fresh()?->getAttributes()
        );
    }

    public function test_repeated_status_requests_are_no_ops(): void
    {
        $investor = $this->createManagedInvestor();
        $timestamp = CarbonImmutable::parse('2026-01-01 00:00:00');
        DB::table('investors')
            ->where('id', $investor->id)
            ->update(['updated_at' => $timestamp]);
        $investor->refresh();

        app(SetInvestorActive::class)->execute(
            $investor,
            ['is_active' => true]
        );

        $this->assertTrue(
            $investor->fresh()?->updated_at?->equalTo($timestamp)
        );

        $inactive = app(SetInvestorActive::class)->execute(
            $investor,
            ['is_active' => false]
        );
        DB::table('investors')
            ->where('id', $investor->id)
            ->update(['updated_at' => $timestamp]);
        $inactive->refresh();

        app(SetInvestorActive::class)->execute(
            $inactive,
            ['is_active' => false]
        );

        $this->assertTrue(
            $inactive->fresh()?->updated_at?->equalTo($timestamp)
        );
    }

    public function test_status_action_rejects_unrelated_or_non_boolean_input(): void
    {
        $investor = $this->createManagedInvestor();

        foreach ([
            ['is_active' => false, 'name' => 'No'],
            ['is_active' => '1'],
            [],
        ] as $input) {
            try {
                app(SetInvestorActive::class)->execute(
                    $investor,
                    $input
                );
                $this->fail('Expected status input rejection.');
            } catch (InvalidInvestor) {
                $this->assertTrue($investor->fresh()?->is_active);
            }
        }
    }

    public function test_status_change_locks_investor_row(): void
    {
        $investor = $this->createManagedInvestor();
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(SetInvestorActive::class)->execute(
            $investor,
            ['is_active' => false]
        );
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertTrue(collect($queries)->contains(
            fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "investors"'
            ) && str_contains(strtolower($query['query']), 'for update')
        ));
    }
}
