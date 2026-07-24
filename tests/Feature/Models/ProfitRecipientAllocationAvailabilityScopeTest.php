<?php

namespace Tests\Feature\Models;

use App\Models\Investor;
use App\Models\ProfitRecipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfitRecipientAllocationAvailabilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_applies_recipient_status_and_structural_linkage_without_coupling_investor_status(): void
    {
        $activeCompany = ProfitRecipient::factory()->company()->create();
        $inactiveCompany = ProfitRecipient::factory()
            ->company()
            ->create(['is_active' => false]);
        $activeInvestor = Investor::factory()->create();
        $activeInvestorRecipient = ProfitRecipient::factory()
            ->for($activeInvestor, 'investor')
            ->create(['is_active' => true]);
        $inactiveInvestor = Investor::factory()->inactive()->create();
        $inactiveInvestorRecipient = ProfitRecipient::factory()
            ->for($inactiveInvestor, 'investor')
            ->create(['is_active' => true]);
        $inactiveRecipient = ProfitRecipient::factory()
            ->create(['is_active' => false]);
        $deletedInvestor = Investor::factory()->create();
        $legacyDeletedInvestorRecipient = ProfitRecipient::factory()
            ->for($deletedInvestor, 'investor')
            ->create(['is_active' => true]);
        $deletedInvestor->delete();

        $recipientIds = ProfitRecipient::query()
            ->availableForAllocation()
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([
            $activeCompany->id,
            $activeInvestorRecipient->id,
            $inactiveInvestorRecipient->id,
        ], $recipientIds);
        $this->assertNotContains($inactiveCompany->id, $recipientIds);
        $this->assertNotContains($inactiveRecipient->id, $recipientIds);
        $this->assertNotContains(
            $legacyDeletedInvestorRecipient->id,
            $recipientIds
        );
    }

    public function test_scope_remains_lazy_and_composes_with_consumer_query_choices(): void
    {
        $investor = Investor::factory()->inactive()->create([
            'name' => 'Independent Status Investor',
        ]);
        $available = ProfitRecipient::factory()
            ->for($investor, 'investor')
            ->create([
                'display_name' => 'Available Recipient',
                'is_active' => true,
            ]);
        $inactive = ProfitRecipient::factory()
            ->company()
            ->create(['is_active' => false]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $query = ProfitRecipient::query()->availableForAllocation();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertSame([], DB::getQueryLog());
        $this->assertNull($query->getQuery()->columns);
        $this->assertNull($query->getQuery()->orders);
        $this->assertSame([], $query->getEagerLoads());
        $this->assertNull($query->getQuery()->limit);

        $recipients = $query
            ->select(['id', 'investor_id', 'display_name'])
            ->whereIn('id', [$available->id, $inactive->id])
            ->with([
                'investor' => fn ($query) => $query
                    ->select(['id', 'name', 'is_active']),
            ])
            ->orderBy('display_name')
            ->limit(1)
            ->get();

        DB::disableQueryLog();

        $recipient = $recipients->sole();
        $this->assertSame($available->id, $recipient->id);
        $this->assertSame(
            ['id', 'investor_id', 'display_name'],
            array_keys($recipient->getAttributes())
        );
        $this->assertTrue($recipient->relationLoaded('investor'));
        $this->assertSame(
            'Independent Status Investor',
            $recipient->investor?->name
        );
        $this->assertFalse((bool) $recipient->investor?->is_active);
    }
}
