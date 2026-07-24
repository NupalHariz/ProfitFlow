<?php

namespace Tests\Feature\Actions\Investors;

use App\Actions\Investors\DeleteInvestor;
use App\Exceptions\Investors\InvalidInvestor;
use App\Models\ProfitDistribution;
use App\Models\ProfitSchemeAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;

class DeleteInvestorTest extends InvestorActionTestCase
{
    public function test_unlinked_investor_is_soft_deleted_under_a_row_lock(): void
    {
        $investor = $this->createManagedInvestor();
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(DeleteInvestor::class)->execute($investor);
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertSoftDeleted('investors', ['id' => $investor->id]);
        $this->assertDatabaseHas('investors', ['id' => $investor->id]);
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
    }

    #[DataProvider('recipientStates')]
    public function test_any_linked_recipient_blocks_deletion(
        bool $recipientActive
    ): void {
        $investor = $this->createManagedInvestor();
        $recipient = $this->recipient($investor, [
            'is_active' => $recipientActive,
        ]);
        $investorState = DB::table('investors')
            ->where('id', $investor->id)
            ->first();
        $recipientState = DB::table('profit_recipients')
            ->where('id', $recipient->id)
            ->first();

        try {
            app(DeleteInvestor::class)->execute($investor);
            $this->fail('Expected linked Investor deletion rejection.');
        } catch (InvalidInvestor $exception) {
            $this->assertStringContainsString(
                'linked to a Profit Recipient',
                $exception->getMessage()
            );
            $this->assertStringContainsString(
                'Deactivate the Investor',
                $exception->getMessage()
            );
        }

        $this->assertEquals(
            $investorState,
            DB::table('investors')
                ->where('id', $investor->id)
                ->first()
        );
        $this->assertEquals(
            $recipientState,
            DB::table('profit_recipients')
                ->where('id', $recipient->id)
                ->first()
        );
        $this->assertFalse($investor->fresh()?->trashed());
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function recipientStates(): array
    {
        return [
            'active recipient' => [true],
            'inactive recipient' => [false],
        ];
    }

    public function test_failed_linked_delete_preserves_allocations_distributions_and_queue(): void
    {
        Queue::fake();
        $investor = $this->createManagedInvestor();
        $recipient = $this->recipient($investor);
        $allocation = ProfitSchemeAllocation::factory()
            ->for($recipient, 'recipient')
            ->create(['percentage' => '100.0000']);
        $distribution = ProfitDistribution::factory()
            ->for($recipient, 'recipient')
            ->create([
                'recipient_name_snapshot' => 'Frozen Recipient',
                'percentage' => '100.0000',
                'amount' => '250.00',
            ]);
        $allocationState = DB::table('profit_scheme_allocations')
            ->where('id', $allocation->id)
            ->first();
        $distributionState = DB::table('profit_distributions')
            ->where('id', $distribution->id)
            ->first();

        try {
            app(DeleteInvestor::class)->execute($investor);
            $this->fail('Expected linked Investor deletion rejection.');
        } catch (InvalidInvestor) {
        }

        $this->assertEquals(
            $allocationState,
            DB::table('profit_scheme_allocations')
                ->where('id', $allocation->id)
                ->first()
        );
        $this->assertEquals(
            $distributionState,
            DB::table('profit_distributions')
                ->where('id', $distribution->id)
                ->first()
        );
        Queue::assertNothingPushed();
    }
}
