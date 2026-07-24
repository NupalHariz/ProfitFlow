<?php

namespace Tests\Feature\Actions\ProfitRecipients;

use App\Actions\ProfitRecipients\UpdateProfitRecipient;
use App\Enums\ProfitRecipientType;
use App\Exceptions\ProfitRecipients\InvalidProfitRecipient;
use App\Exceptions\ProfitRecipients\ProfitRecipientCodeAlreadyExists;
use Illuminate\Support\Facades\DB;

class UpdateProfitRecipientTest extends ProfitRecipientActionTestCase
{
    public function test_update_changes_only_code_and_display_name_under_lock(): void
    {
        $investor = $this->investor(['is_active' => false]);
        $recipient = $this->createManagedRecipient([
            'investor_id' => $investor->id,
            'is_active' => false,
        ]);
        $investorState = DB::table('investors')
            ->where('id', $investor->id)
            ->first();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $updated = app(UpdateProfitRecipient::class)->execute(
            $recipient,
            [
                'code' => '  Current-Code  ',
                'display_name' => '  Current Display Name  ',
            ]
        );
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertSame('Current-Code', $updated->code);
        $this->assertSame(
            'Current Display Name',
            $updated->display_name
        );
        $this->assertSame(
            ProfitRecipientType::Investor,
            $updated->type
        );
        $this->assertSame($investor->id, $updated->investor_id);
        $this->assertFalse($updated->is_active);
        $this->assertEquals(
            $investorState,
            DB::table('investors')
                ->where('id', $investor->id)
                ->first()
        );
        $this->assertTrue(collect($queries)->contains(
            fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "profit_recipients"'
            ) && str_contains(strtolower($query['query']), 'for update')
        ));
    }

    public function test_unchanged_code_is_allowed_but_an_exact_duplicate_is_mapped(): void
    {
        $existing = $this->createManagedRecipient([
            'code' => 'EXISTING-CODE',
        ]);
        $target = $this->createManagedRecipient([
            'code' => 'TARGET-CODE',
        ]);

        app(UpdateProfitRecipient::class)->execute($target, [
            'code' => 'TARGET-CODE',
            'display_name' => 'Unchanged Code',
        ]);

        try {
            app(UpdateProfitRecipient::class)->execute($target, [
                'code' => $existing->code,
                'display_name' => 'Duplicate Attempt',
            ]);
            $this->fail('Expected duplicate code rejection.');
        } catch (ProfitRecipientCodeAlreadyExists $exception) {
            $this->assertStringContainsString(
                'EXISTING-CODE',
                $exception->getMessage()
            );
        }

        $this->assertSame('TARGET-CODE', $target->fresh()?->code);
        $this->assertSame(
            'Unchanged Code',
            $target->fresh()?->display_name
        );
    }

    public function test_update_rejects_identity_status_and_unrelated_fields_atomically(): void
    {
        $recipient = $this->createManagedRecipient();
        $before = DB::table('profit_recipients')
            ->where('id', $recipient->id)
            ->first();

        foreach ([
            [
                'code' => 'CHANGED',
                'display_name' => 'Changed',
                'type' => ProfitRecipientType::Company,
            ],
            [
                'code' => 'CHANGED',
                'display_name' => 'Changed',
                'investor_id' => null,
            ],
            [
                'code' => 'CHANGED',
                'display_name' => 'Changed',
                'is_active' => false,
            ],
            [
                'code' => '',
                'display_name' => 'Changed',
            ],
            [
                'code' => 'CHANGED',
                'display_name' => 'Changed',
                'allocations' => [],
            ],
        ] as $input) {
            try {
                app(UpdateProfitRecipient::class)->execute(
                    $recipient,
                    $input
                );
                $this->fail('Expected invalid recipient update.');
            } catch (InvalidProfitRecipient) {
                $this->assertEquals(
                    $before,
                    DB::table('profit_recipients')
                        ->where('id', $recipient->id)
                        ->first()
                );
            }
        }
    }
}
