<?php

namespace Tests\Feature\Actions\ProfitRecipients;

use App\Actions\Investors\DeleteInvestor;
use App\Actions\ProfitRecipients\CreateProfitRecipient;
use App\Enums\ProfitRecipientType;
use App\Exceptions\Investors\InvalidInvestor;
use App\Exceptions\ProfitRecipients\InvalidProfitRecipient;
use App\Exceptions\ProfitRecipients\InvestorLinkUnavailable;
use App\Exceptions\ProfitRecipients\ProfitRecipientCodeAlreadyExists;
use App\Models\Investor;
use App\Models\ProfitRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;

class CreateProfitRecipientTest extends ProfitRecipientActionTestCase
{
    #[DataProvider('investorStates')]
    public function test_investor_recipient_is_normalized_and_created_under_compatible_lock(
        bool $investorActive
    ): void {
        $investor = $this->investor([
            'name' => 'Original Investor',
            'is_active' => $investorActive,
        ]);
        $investorState = DB::table('investors')
            ->where('id', $investor->id)
            ->first();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $recipient = app(CreateProfitRecipient::class)->execute([
            'code' => '  Mixed-Case-Code  ',
            'type' => ProfitRecipientType::Investor,
            'investor_id' => $investor->id,
            'display_name' => '  Allocation Identity  ',
            'is_active' => false,
        ]);
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertSame('Mixed-Case-Code', $recipient->code);
        $this->assertSame(
            ProfitRecipientType::Investor,
            $recipient->type
        );
        $this->assertSame($investor->id, $recipient->investor_id);
        $this->assertSame(
            'Allocation Identity',
            $recipient->display_name
        );
        $this->assertFalse($recipient->is_active);
        $this->assertEquals(
            $investorState,
            DB::table('investors')
                ->where('id', $investor->id)
                ->first()
        );
        $this->assertTrue(collect($queries)->contains(
            fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "investors"'
            ) && str_contains(strtolower($query['query']), 'for update')
        ));
        $this->assertTrue(collect($queries)->contains(
            fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "profit_recipients"'
            ) && str_contains(strtolower($query['query']), 'exists')
        ));
        $this->assertDatabaseCount('profit_scheme_allocations', 0);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function investorStates(): array
    {
        return [
            'active Investor' => [true],
            'inactive Investor' => [false],
        ];
    }

    public function test_multiple_company_recipients_are_created_without_investors(): void
    {
        $first = $this->createManagedRecipient([
            'code' => 'COMPANY-OPERATIONS',
            'type' => ProfitRecipientType::Company,
            'investor_id' => null,
            'display_name' => 'Company Operations',
        ]);
        $second = $this->createManagedRecipient([
            'code' => 'COMPANY-RESERVE',
            'type' => ProfitRecipientType::Company,
            'investor_id' => null,
            'display_name' => 'Company Reserve',
        ]);

        $this->assertSame(
            ProfitRecipientType::Company,
            $first->type
        );
        $this->assertNull($first->investor_id);
        $this->assertNull($second->investor_id);
        $this->assertDatabaseCount('profit_recipients', 2);
        $this->assertDatabaseCount('investors', 0);
    }

    #[DataProvider('invalidCreationData')]
    public function test_invalid_or_protected_creation_input_is_rejected(
        array $overrides
    ): void {
        $this->expectException(InvalidProfitRecipient::class);

        app(CreateProfitRecipient::class)->execute(
            $this->recipientData($overrides)
        );
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidCreationData(): array
    {
        return [
            'blank code' => [['code' => ' ']],
            'long code' => [['code' => str_repeat('x', 81)]],
            'invalid type' => [['type' => 'partner']],
            'investor without id' => [[
                'type' => ProfitRecipientType::Investor,
                'investor_id' => null,
            ]],
            'company with id' => [[
                'type' => ProfitRecipientType::Company,
                'investor_id' => 1,
            ]],
            'blank display name' => [['display_name' => ' ']],
            'long display name' => [[
                'display_name' => str_repeat('x', 161),
            ]],
            'non-boolean status' => [['is_active' => '1']],
            'protected field' => [['allocations' => []]],
        ];
    }

    public function test_missing_soft_deleted_and_already_linked_investors_are_rejected(): void
    {
        $deleted = $this->investor();
        $deleted->delete();
        $linked = $this->investor();
        ProfitRecipient::factory()->for($linked)->create();

        foreach ([
            999999,
            $deleted->id,
            $linked->id,
        ] as $investorId) {
            try {
                app(CreateProfitRecipient::class)->execute(
                    $this->recipientData([
                        'code' => "RECIPIENT-{$investorId}",
                        'investor_id' => $investorId,
                    ])
                );
                $this->fail('Expected unavailable Investor rejection.');
            } catch (InvestorLinkUnavailable) {
                $this->assertDatabaseMissing('profit_recipients', [
                    'code' => "RECIPIENT-{$investorId}",
                ]);
            }
        }
    }

    public function test_code_uniqueness_is_exact_case_sensitive_and_named_race_is_mapped(): void
    {
        $this->createManagedRecipient([
            'code' => 'Case-Sensitive',
        ]);
        $differentCase = $this->createManagedRecipient([
            'code' => 'case-sensitive',
        ]);

        $this->assertSame('case-sensitive', $differentCase->code);

        try {
            $this->createManagedRecipient([
                'code' => 'Case-Sensitive',
            ]);
            $this->fail('Expected duplicate code rejection.');
        } catch (ProfitRecipientCodeAlreadyExists $exception) {
            $this->assertStringContainsString(
                'Case-Sensitive',
                $exception->getMessage()
            );
        }
    }

    public function test_named_investor_unique_constraint_race_maps_to_link_error(): void
    {
        $investor = $this->investor();
        $event = 'eloquent.creating: '.ProfitRecipient::class;
        Event::listen(
            $event,
            function (ProfitRecipient $creating) use ($investor): void {
                if ($creating->code !== 'RACE-TARGET') {
                    return;
                }

                DB::table('profit_recipients')->insert([
                    'code' => 'RACE-WINNER',
                    'type' => ProfitRecipientType::Investor->value,
                    'investor_id' => $investor->id,
                    'display_name' => 'Concurrent Winner',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        );

        try {
            app(CreateProfitRecipient::class)->execute(
                $this->recipientData([
                    'code' => 'RACE-TARGET',
                    'investor_id' => $investor->id,
                ])
            );
            $this->fail('Expected Investor-link race mapping.');
        } catch (InvestorLinkUnavailable $exception) {
            $this->assertStringContainsString(
                'already linked',
                $exception->getMessage()
            );
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseCount('profit_recipients', 0);
    }

    public function test_create_and_delete_investor_serialize_to_safe_outcomes(): void
    {
        $createFirst = $this->investor();
        $recipient = app(CreateProfitRecipient::class)->execute(
            $this->recipientData([
                'code' => 'CREATE-FIRST',
                'investor_id' => $createFirst->id,
            ])
        );

        try {
            app(DeleteInvestor::class)->execute($createFirst);
            $this->fail('Expected linked Investor deletion rejection.');
        } catch (InvalidInvestor) {
            $this->assertFalse($createFirst->fresh()?->trashed());
            $this->assertSame(
                $recipient->id,
                $createFirst->fresh()?->profitRecipient?->id
            );
        }

        $deleteFirst = $this->investor();
        app(DeleteInvestor::class)->execute($deleteFirst);

        try {
            app(CreateProfitRecipient::class)->execute(
                $this->recipientData([
                    'code' => 'DELETE-FIRST',
                    'investor_id' => $deleteFirst->id,
                ])
            );
            $this->fail('Expected soft-deleted Investor rejection.');
        } catch (InvestorLinkUnavailable) {
            $this->assertTrue(
                Investor::withTrashed()->findOrFail($deleteFirst->id)
                    ->trashed()
            );
            $this->assertDatabaseMissing('profit_recipients', [
                'code' => 'DELETE-FIRST',
            ]);
        }
    }
}
