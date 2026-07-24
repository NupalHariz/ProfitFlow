<?php

namespace Tests\Feature\Actions\Investors;

use App\Actions\Investors\UpdateInvestor;
use App\Exceptions\Investors\InvalidInvestor;
use Illuminate\Support\Facades\DB;

class UpdateInvestorTest extends InvestorActionTestCase
{
    public function test_it_updates_only_profile_fields_and_preserves_status_and_recipient(): void
    {
        $investor = $this->createManagedInvestor([
            'is_active' => false,
        ]);
        $recipient = $this->recipient($investor, [
            'code' => 'INVESTOR-NF',
            'display_name' => 'Frozen Recipient Name',
            'is_active' => true,
        ]);
        $recipientState = $recipient->getAttributes();

        $updated = app(UpdateInvestor::class)->execute($investor, [
            'name' => '  Current Investor Name  ',
            'email' => '  current@example.test  ',
            'phone' => ' ',
        ]);

        $this->assertSame('Current Investor Name', $updated->name);
        $this->assertSame('current@example.test', $updated->email);
        $this->assertNull($updated->phone);
        $this->assertFalse($updated->is_active);
        $this->assertEquals(
            $recipientState,
            $recipient->fresh()?->getAttributes()
        );
    }

    public function test_it_normalizes_international_phone_to_local_digits(): void
    {
        $investor = $this->createManagedInvestor();

        $updated = app(UpdateInvestor::class)->execute($investor, [
            'name' => 'Updated Investor',
            'email' => null,
            'phone' => '+62 812 3456 7890',
        ]);

        $this->assertSame('081234567890', $updated->phone);
    }

    public function test_invalid_update_is_atomic_and_rejects_status_or_unknown_fields(): void
    {
        $investor = $this->createManagedInvestor();
        $before = DB::table('investors')
            ->where('id', $investor->id)
            ->first();

        foreach ([
            [
                'name' => 'Changed',
                'email' => null,
                'phone' => null,
                'is_active' => false,
            ],
            [
                'name' => 'Changed',
                'email' => null,
                'phone' => null,
                'profit_recipient_id' => 123,
            ],
            [
                'name' => '',
                'email' => null,
                'phone' => null,
            ],
        ] as $input) {
            try {
                app(UpdateInvestor::class)->execute(
                    $investor,
                    $input
                );
                $this->fail('Expected invalid Investor update.');
            } catch (InvalidInvestor) {
                $this->assertEquals(
                    $before,
                    DB::table('investors')
                        ->where('id', $investor->id)
                        ->first()
                );
            }
        }
    }

    public function test_update_locks_investor_row(): void
    {
        $investor = $this->createManagedInvestor();
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(UpdateInvestor::class)->execute($investor, [
            'name' => 'Locked Investor',
            'email' => null,
            'phone' => null,
        ]);
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
