<?php

namespace Tests\Feature\Http\Investors;

use App\Models\Investor;
use App\Models\ProfitDistribution;
use App\Models\ProfitSchemeAllocation;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class InvestorManagementTest extends InvestorHttpTestCase
{
    public function test_verified_user_can_create_normalized_investor_without_recipient(): void
    {
        $response = $this->actingAs($this->user())
            ->post(route('investors.store'), [
                'name' => '  Naufal Pratama  ',
                'email' => '  naufal@example.test  ',
                'phone' => '  62 (812) 0000-1001  ',
                'is_active' => '0',
            ]);
        $investor = Investor::query()->sole();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('investors.show', $investor));
        $this->assertSame('Naufal Pratama', $investor->name);
        $this->assertSame('naufal@example.test', $investor->email);
        $this->assertSame('081200001001', $investor->phone);
        $this->assertFalse($investor->is_active);
        $this->assertDatabaseCount('profit_recipients', 0);
    }

    public function test_blank_options_normalize_to_null_and_duplicate_email_is_allowed(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('investors.store'), $this->investorPayload([
                'name' => 'First',
                'email' => 'shared@example.test',
                'phone' => ' ',
            ]))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('investors.store'), $this->investorPayload([
                'name' => 'Second',
                'email' => 'shared@example.test',
                'phone' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            2,
            Investor::query()
                ->where('email', 'shared@example.test')
                ->count()
        );
        $this->assertSame(
            [null, null],
            Investor::query()
                ->orderBy('id')
                ->pluck('phone')
                ->all()
        );
    }

    #[DataProvider('invalidStoreInput')]
    public function test_store_validation_is_enforced(
        array $overrides,
        string $field
    ): void {
        $this->actingAs($this->user())
            ->post(
                route('investors.store'),
                $this->investorPayload($overrides)
            )
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('investors', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidStoreInput(): array
    {
        return [
            'blank name' => [['name' => ' '], 'name'],
            'long name' => [['name' => str_repeat('x', 161)], 'name'],
            'invalid email' => [['email' => 'invalid'], 'email'],
            'long phone' => [['phone' => str_repeat('1', 41)], 'phone'],
            'unsupported phone country' => [
                ['phone' => '+1 555 010 1000'],
                'phone',
            ],
            'phone with letters' => [['phone' => '0812-CALL-NOW'], 'phone'],
            'invalid status' => [['is_active' => 'yes'], 'is_active'],
        ];
    }

    public function test_store_rejects_protected_fields(): void
    {
        $this->actingAs($this->user())
            ->post(route('investors.store'), [
                ...$this->investorPayload(),
                'id' => 999,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => now(),
                'profit_recipient' => ['id' => 1],
                'profit_recipient_id' => 1,
                'allocations' => [['id' => 1]],
                'distributions' => [['id' => 1]],
            ])
            ->assertSessionHasErrors([
                'id',
                'created_at',
                'updated_at',
                'deleted_at',
                'profit_recipient',
                'profit_recipient_id',
                'allocations',
                'distributions',
            ]);

        $this->assertDatabaseCount('investors', 0);
    }

    public function test_general_update_changes_profile_only_and_preserves_linked_recipient(): void
    {
        $investor = $this->investor(['is_active' => false]);
        $recipient = $this->recipient($investor, [
            'code' => 'INVESTOR-READONLY',
            'display_name' => 'Read-only Recipient',
            'is_active' => true,
        ]);
        $recipientState = $recipient->getAttributes();

        $this->actingAs($this->user())
            ->patch(route('investors.update', $investor), [
                'name' => '  Updated Investor  ',
                'email' => ' updated@example.test ',
                'phone' => '0813 4567-8901',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('investors.show', $investor));

        $investor->refresh();
        $this->assertSame('Updated Investor', $investor->name);
        $this->assertSame('updated@example.test', $investor->email);
        $this->assertSame('081345678901', $investor->phone);
        $this->assertFalse($investor->is_active);
        $this->assertEquals(
            $recipientState,
            $recipient->fresh()?->getAttributes()
        );
    }

    public function test_general_update_rejects_status_and_protected_fields(): void
    {
        $investor = $this->investor();
        $before = DB::table('investors')
            ->where('id', $investor->id)
            ->first();

        $this->actingAs($this->user())
            ->patch(route('investors.update', $investor), [
                ...$this->investorUpdatePayload(),
                'is_active' => false,
                'deleted_at' => now(),
                'profit_recipient_id' => 1,
                'allocations' => [['id' => 1]],
                'distributions' => [['id' => 1]],
            ])
            ->assertSessionHasErrors([
                'is_active',
                'deleted_at',
                'profit_recipient_id',
                'allocations',
                'distributions',
            ]);

        $this->assertEquals(
            $before,
            DB::table('investors')
                ->where('id', $investor->id)
                ->first()
        );
    }

    public function test_status_endpoint_is_independent_and_rejects_other_fields(): void
    {
        $investor = $this->investor();
        $recipient = $this->recipient($investor, [
            'is_active' => true,
        ]);
        $recipientState = $recipient->getAttributes();
        $user = $this->user();

        $this->actingAs($user)
            ->from(route('investors.show', $investor))
            ->patch(route('investors.update-status', $investor), [
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('investors.show', $investor));
        $this->assertFalse((bool) $investor->fresh()?->is_active);
        $this->assertEquals(
            $recipientState,
            $recipient->fresh()?->getAttributes()
        );

        $this->actingAs($user)
            ->patch(route('investors.update-status', $investor), [
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();
        $this->assertTrue((bool) $investor->fresh()?->is_active);

        $this->actingAs($user)
            ->patch(route('investors.update-status', $investor), [
                'is_active' => false,
                'name' => 'Not allowed',
            ])
            ->assertSessionHasErrors('name');
        $this->assertTrue((bool) $investor->fresh()?->is_active);
    }

    public function test_detail_is_bounded_and_contains_read_only_recipient_counts(): void
    {
        $investor = $this->investor([
            'name' => 'Detailed Investor',
            'email' => 'detail@example.test',
            'phone' => '+6281211111001',
            'is_active' => false,
        ]);
        $recipient = $this->recipient($investor, [
            'code' => 'INVESTOR-DETAIL',
            'display_name' => 'Detailed Allocation Identity',
            'is_active' => true,
        ]);
        ProfitSchemeAllocation::factory()
            ->count(2)
            ->for($recipient, 'recipient')
            ->create();
        ProfitDistribution::factory()
            ->count(3)
            ->for($recipient, 'recipient')
            ->create();

        $this->actingAs($this->user())
            ->get(route('investors.show', $investor))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('investors/show')
                    ->has(
                        'investor',
                        fn (Assert $detail) => $detail
                            ->hasAll([
                                'id',
                                'name',
                                'email',
                                'phone',
                                'is_active',
                                'created_at',
                                'updated_at',
                            ])
                            ->where('id', $investor->id)
                            ->where('name', 'Detailed Investor')
                            ->where('is_active', false)
                            ->missing('deleted_at')
                            ->missing('profit_recipient')
                    )
                    ->has(
                        'linked_recipient',
                        fn (Assert $linked) => $linked
                            ->hasAll([
                                'id',
                                'code',
                                'type',
                                'display_name',
                                'is_active',
                            ])
                            ->where('id', $recipient->id)
                            ->where('code', 'INVESTOR-DETAIL')
                            ->where(
                                'display_name',
                                'Detailed Allocation Identity'
                            )
                            ->where('is_active', true)
                            ->missing('allocations')
                            ->missing('distributions')
                    )
                    ->where('allocation_summary.allocation_count', 2)
                    ->where('allocation_summary.distribution_count', 3)
            );
    }

    public function test_detail_and_edit_show_not_linked_without_mutation(): void
    {
        $investor = $this->investor();
        $before = DB::table('investors')
            ->where('id', $investor->id)
            ->first();
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('investors.show', $investor))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('linked_recipient', null)
                    ->where('allocation_summary.allocation_count', 0)
                    ->where('allocation_summary.distribution_count', 0)
            );
        $this->actingAs($user)
            ->get(route('investors.edit', $investor))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('linked_recipient', null)
            );

        $this->assertEquals(
            $before,
            DB::table('investors')
                ->where('id', $investor->id)
                ->first()
        );
    }

    public function test_detail_query_count_is_bounded(): void
    {
        $investor = $this->investor();
        $recipient = $this->recipient($investor);
        ProfitSchemeAllocation::factory()
            ->count(10)
            ->for($recipient, 'recipient')
            ->create();
        ProfitDistribution::factory()
            ->count(10)
            ->for($recipient, 'recipient')
            ->create();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->user())
            ->get(route('investors.show', $investor))
            ->assertOk();
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(7, $queryCount);
    }

    public function test_unlinked_investor_can_be_soft_deleted_and_disappears_from_management(): void
    {
        $investor = $this->investor();
        $user = $this->user();

        $this->actingAs($user)
            ->delete(route('investors.destroy', $investor))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('investors.index'));

        $this->assertSoftDeleted('investors', ['id' => $investor->id]);
        $this->actingAs($user)
            ->get(route('investors.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('investors.data', 0)
            );
    }

    #[DataProvider('recipientStates')]
    public function test_linked_investor_deletion_is_rejected_without_mutation(
        bool $recipientActive
    ): void {
        $investor = $this->investor();
        $recipient = $this->recipient($investor, [
            'is_active' => $recipientActive,
        ]);
        $investorBefore = DB::table('investors')
            ->where('id', $investor->id)
            ->first();
        $recipientBefore = DB::table('profit_recipients')
            ->where('id', $recipient->id)
            ->first();

        $this->actingAs($this->user())
            ->from(route('investors.show', $investor))
            ->delete(route('investors.destroy', $investor))
            ->assertRedirect(route('investors.show', $investor))
            ->assertSessionHasErrors('investor');

        $this->assertEquals(
            $investorBefore,
            DB::table('investors')
                ->where('id', $investor->id)
                ->first()
        );
        $this->assertEquals(
            $recipientBefore,
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
}
