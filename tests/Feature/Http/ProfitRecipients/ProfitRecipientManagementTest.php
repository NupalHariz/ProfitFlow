<?php

namespace Tests\Feature\Http\ProfitRecipients;

use App\Enums\ProfitRecipientType;
use App\Models\Investor;
use App\Models\ProfitDistribution;
use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class ProfitRecipientManagementTest extends ProfitRecipientHttpTestCase
{
    public function test_create_page_has_bounded_unlinked_investors_and_defaults(): void
    {
        $active = $this->investor(['name' => 'A Active']);
        $inactive = $this->investor([
            'name' => 'B Inactive',
            'is_active' => false,
        ]);
        $linked = $this->investor(['name' => 'C Linked']);
        $this->recipient($linked);
        $deleted = $this->investor(['name' => 'D Deleted']);
        $deleted->delete();
        Investor::factory()->count(250)->create();

        $this->actingAs($this->user())
            ->get(route('profit-recipients.create'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('profit-recipients/create')
                    ->has('type_options', 2)
                    ->has('investors', 250)
                    ->where('investor_option_limit', 250)
                    ->where('defaults.code', '')
                    ->where('defaults.type', 'investor')
                    ->where('defaults.investor_id', null)
                    ->where('defaults.display_name', '')
                    ->where('defaults.is_active', true)
                    ->where(
                        'investors',
                        fn (mixed $options): bool => collect($options)
                            ->contains('id', $active->id)
                            && collect($options)
                                ->contains('id', $inactive->id)
                            && ! collect($options)
                                ->contains('id', $linked->id)
                            && ! collect($options)
                                ->contains('id', $deleted->id)
                    )
            );
    }

    #[DataProvider('investorStates')]
    public function test_verified_user_can_create_normalized_investor_recipient(
        bool $investorActive,
        bool $recipientActive
    ): void {
        $investor = $this->investor([
            'name' => 'Original Investor',
            'is_active' => $investorActive,
        ]);
        $investorBefore = DB::table('investors')
            ->where('id', $investor->id)
            ->first();

        $response = $this->actingAs($this->user())
            ->post(route('profit-recipients.store'), [
                'code' => '  Mixed-Code  ',
                'type' => 'investor',
                'investor_id' => (string) $investor->id,
                'display_name' => '  Allocation Identity  ',
                'is_active' => $recipientActive ? '1' : '0',
            ]);
        $recipient = ProfitRecipient::query()->sole();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('profit-recipients.show', $recipient)
            );
        $this->assertSame('Mixed-Code', $recipient->code);
        $this->assertSame(
            ProfitRecipientType::Investor,
            $recipient->type
        );
        $this->assertSame($investor->id, $recipient->investor_id);
        $this->assertSame(
            'Allocation Identity',
            $recipient->display_name
        );
        $this->assertSame($recipientActive, $recipient->is_active);
        $this->assertEquals(
            $investorBefore,
            DB::table('investors')
                ->where('id', $investor->id)
                ->first()
        );
        $this->assertDatabaseCount('profit_scheme_allocations', 0);
    }

    /**
     * @return array<string, array{bool, bool}>
     */
    public static function investorStates(): array
    {
        return [
            'active Investor and recipient' => [true, true],
            'inactive Investor and recipient' => [false, false],
        ];
    }

    public function test_company_recipients_are_created_without_investors_and_may_be_multiple(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(
                route('profit-recipients.store'),
                $this->companyPayload()
            )
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(
                route('profit-recipients.store'),
                $this->companyPayload([
                    'code' => 'COMPANY-RESERVE',
                    'display_name' => 'Company Reserve',
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('profit_recipients', 2);
        $this->assertDatabaseCount('investors', 0);
        $this->assertSame(
            [null, null],
            ProfitRecipient::query()
                ->orderBy('id')
                ->pluck('investor_id')
                ->all()
        );
    }

    public function test_investor_linkage_validation_rejects_missing_deleted_linked_and_company_ids(): void
    {
        $deleted = $this->investor();
        $deleted->delete();
        $linked = $this->investor();
        $this->recipient($linked);
        $companyInvestor = $this->investor();
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('profit-recipients.store'), [
                ...$this->recipientPayload($companyInvestor),
                'investor_id' => null,
            ])
            ->assertSessionHasErrors('investor_id');
        $this->actingAs($user)
            ->post(route('profit-recipients.store'), [
                ...$this->recipientPayload($companyInvestor),
                'code' => 'MISSING-ID',
                'investor_id' => 999999,
            ])
            ->assertSessionHasErrors('investor_id');
        $this->actingAs($user)
            ->post(route('profit-recipients.store'), [
                ...$this->recipientPayload($companyInvestor),
                'code' => 'DELETED-ID',
                'investor_id' => $deleted->id,
            ])
            ->assertSessionHasErrors('investor_id');
        $this->actingAs($user)
            ->post(route('profit-recipients.store'), [
                ...$this->recipientPayload($linked),
                'code' => 'LINKED-ID',
            ])
            ->assertSessionHasErrors('investor_id');
        $this->actingAs($user)
            ->post(route('profit-recipients.store'), [
                ...$this->companyPayload([
                    'code' => 'COMPANY-WITH-ID',
                ]),
                'investor_id' => $companyInvestor->id,
            ])
            ->assertSessionHasErrors('investor_id');
    }

    #[DataProvider('invalidStoreInput')]
    public function test_store_validation_is_enforced(
        array $overrides,
        string $field
    ): void {
        $investor = $this->investor();

        $this->actingAs($this->user())
            ->post(
                route('profit-recipients.store'),
                $this->recipientPayload($investor, $overrides)
            )
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('profit_recipients', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidStoreInput(): array
    {
        return [
            'blank code' => [['code' => ' '], 'code'],
            'long code' => [
                ['code' => str_repeat('x', 81)],
                'code',
            ],
            'invalid type' => [['type' => 'partner'], 'type'],
            'blank display name' => [
                ['display_name' => ' '],
                'display_name',
            ],
            'long display name' => [
                ['display_name' => str_repeat('x', 161)],
                'display_name',
            ],
            'invalid status' => [
                ['is_active' => 'yes'],
                'is_active',
            ],
        ];
    }

    public function test_duplicate_code_is_rejected_but_case_variant_is_preserved(): void
    {
        $firstInvestor = $this->investor();
        $secondInvestor = $this->investor();
        $thirdInvestor = $this->investor();
        $user = $this->user();

        $this->actingAs($user)
            ->post(
                route('profit-recipients.store'),
                $this->recipientPayload($firstInvestor, [
                    'code' => 'Case-Code',
                ])
            )
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(
                route('profit-recipients.store'),
                $this->recipientPayload($secondInvestor, [
                    'code' => 'case-code',
                ])
            )
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(
                route('profit-recipients.store'),
                $this->recipientPayload($thirdInvestor, [
                    'code' => 'Case-Code',
                ])
            )
            ->assertSessionHasErrors('code');

        $this->assertSame(
            ['Case-Code', 'case-code'],
            ProfitRecipient::query()
                ->orderBy('id')
                ->pluck('code')
                ->all()
        );
    }

    public function test_store_rejects_protected_fields(): void
    {
        $investor = $this->investor();

        $this->actingAs($this->user())
            ->post(route('profit-recipients.store'), [
                ...$this->recipientPayload($investor),
                'id' => 999,
                'created_at' => now(),
                'updated_at' => now(),
                'investor' => ['id' => $investor->id],
                'allocations' => [['id' => 1]],
                'distributions' => [['id' => 1]],
                'usage_summary' => ['allocation_count' => 10],
            ])
            ->assertSessionHasErrors([
                'id',
                'created_at',
                'updated_at',
                'investor',
                'allocations',
                'distributions',
                'usage_summary',
            ]);

        $this->assertDatabaseCount('profit_recipients', 0);
    }

    public function test_general_update_changes_profile_only_and_preserves_identity_status_and_history(): void
    {
        $investor = $this->investor(['is_active' => false]);
        $recipient = $this->recipient($investor, [
            'is_active' => false,
        ]);
        $allocation = ProfitSchemeAllocation::factory()
            ->for($recipient, 'recipient')
            ->create();
        $distribution = ProfitDistribution::factory()
            ->for($recipient, 'recipient')
            ->create([
                'recipient_name_snapshot' => 'Frozen Name',
            ]);
        $investorBefore = DB::table('investors')
            ->where('id', $investor->id)
            ->first();
        $allocationBefore = DB::table('profit_scheme_allocations')
            ->where('id', $allocation->id)
            ->first();
        $distributionBefore = DB::table('profit_distributions')
            ->where('id', $distribution->id)
            ->first();

        $this->actingAs($this->user())
            ->patch(route('profit-recipients.update', $recipient), [
                'code' => '  UPDATED-CODE  ',
                'display_name' => '  Updated Display  ',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('profit-recipients.show', $recipient)
            );

        $recipient->refresh();
        $this->assertSame('UPDATED-CODE', $recipient->code);
        $this->assertSame('Updated Display', $recipient->display_name);
        $this->assertSame(
            ProfitRecipientType::Investor,
            $recipient->type
        );
        $this->assertSame($investor->id, $recipient->investor_id);
        $this->assertFalse($recipient->is_active);
        $this->assertEquals(
            $investorBefore,
            DB::table('investors')
                ->where('id', $investor->id)
                ->first()
        );
        $this->assertEquals(
            $allocationBefore,
            DB::table('profit_scheme_allocations')
                ->where('id', $allocation->id)
                ->first()
        );
        $this->assertEquals(
            $distributionBefore,
            DB::table('profit_distributions')
                ->where('id', $distribution->id)
                ->first()
        );
    }

    public function test_update_accepts_unchanged_code_and_rejects_duplicate_code(): void
    {
        $existing = $this->recipient(null, [
            'code' => 'EXISTING-CODE',
        ]);
        $target = $this->recipient(null, [
            'code' => 'TARGET-CODE',
        ]);
        $user = $this->user();

        $this->actingAs($user)
            ->patch(
                route('profit-recipients.update', $target),
                $this->updatePayload(['code' => 'TARGET-CODE'])
            )
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->patch(
                route('profit-recipients.update', $target),
                $this->updatePayload(['code' => $existing->code])
            )
            ->assertSessionHasErrors('code');

        $this->assertSame('TARGET-CODE', $target->fresh()?->code);
    }

    public function test_general_update_rejects_type_linkage_status_and_protected_fields(): void
    {
        $recipient = $this->recipient();
        $replacementInvestor = $this->investor();
        $before = DB::table('profit_recipients')
            ->where('id', $recipient->id)
            ->first();

        $this->actingAs($this->user())
            ->patch(route('profit-recipients.update', $recipient), [
                ...$this->updatePayload(),
                'type' => 'company',
                'investor_id' => $replacementInvestor->id,
                'is_active' => false,
                'investor' => ['id' => $replacementInvestor->id],
                'allocations' => [['id' => 1]],
                'distributions' => [['id' => 1]],
            ])
            ->assertSessionHasErrors([
                'type',
                'investor_id',
                'is_active',
                'investor',
                'allocations',
                'distributions',
            ]);

        $this->assertEquals(
            $before,
            DB::table('profit_recipients')
                ->where('id', $recipient->id)
                ->first()
        );
    }

    public function test_status_endpoint_is_independent_idempotent_and_rejects_profile_fields(): void
    {
        $investor = $this->investor(['is_active' => true]);
        $recipient = $this->recipient($investor);
        $investorBefore = DB::table('investors')
            ->where('id', $investor->id)
            ->first();
        $user = $this->user();

        $this->actingAs($user)
            ->from(route('profit-recipients.show', $recipient))
            ->patch(
                route('profit-recipients.update-status', $recipient),
                ['is_active' => false]
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('profit-recipients.show', $recipient)
            );
        $this->assertFalse((bool) $recipient->fresh()?->is_active);

        $timestamp = CarbonImmutable::parse('2026-01-01 00:00:00');
        DB::table('profit_recipients')
            ->where('id', $recipient->id)
            ->update(['updated_at' => $timestamp]);
        $recipient->refresh();
        $this->actingAs($user)
            ->patch(
                route('profit-recipients.update-status', $recipient),
                ['is_active' => false]
            )
            ->assertSessionHasNoErrors();
        $this->assertTrue(
            $recipient->fresh()?->updated_at?->equalTo($timestamp)
        );

        $this->actingAs($user)
            ->patch(
                route('profit-recipients.update-status', $recipient),
                [
                    'is_active' => true,
                    'display_name' => 'Not allowed',
                ]
            )
            ->assertSessionHasErrors('display_name');
        $this->assertFalse((bool) $recipient->fresh()?->is_active);
        $this->assertEquals(
            $investorBefore,
            DB::table('investors')
                ->where('id', $investor->id)
                ->first()
        );
    }

    public function test_detail_has_read_only_identity_investor_and_bounded_usage_counts(): void
    {
        $investor = $this->investor([
            'name' => 'Detail Investor',
            'email' => 'detail@example.test',
            'phone' => '+62 811 0000',
            'is_active' => false,
        ]);
        $recipient = $this->recipient($investor, [
            'code' => 'RECIPIENT-DETAIL',
            'display_name' => 'Detail Identity',
            'is_active' => true,
        ]);
        $draftVersion = ProfitSchemeVersion::factory()->create();
        $publishedVersion = ProfitSchemeVersion::factory()
            ->published()
            ->create();
        ProfitSchemeAllocation::factory()
            ->for($draftVersion, 'version')
            ->for($recipient, 'recipient')
            ->create();
        ProfitSchemeAllocation::factory()
            ->for($publishedVersion, 'version')
            ->for($recipient, 'recipient')
            ->create();
        ProfitDistribution::factory()
            ->count(3)
            ->for($recipient, 'recipient')
            ->create();

        $this->actingAs($this->user())
            ->get(route('profit-recipients.show', $recipient))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('profit-recipients/show')
                    ->has(
                        'recipient',
                        fn (Assert $detail) => $detail
                            ->hasAll([
                                'id',
                                'code',
                                'type',
                                'display_name',
                                'is_active',
                                'created_at',
                                'updated_at',
                            ])
                            ->where('id', $recipient->id)
                            ->where('type', 'investor')
                            ->missing('investor_id')
                            ->missing('allocations')
                            ->missing('distributions')
                    )
                    ->has(
                        'investor',
                        fn (Assert $summary) => $summary
                            ->hasAll([
                                'id',
                                'name',
                                'email',
                                'phone',
                                'is_active',
                            ])
                            ->where('id', $investor->id)
                            ->where('is_active', false)
                            ->missing('profit_recipient')
                    )
                    ->where('usage_summary.allocation_count', 2)
                    ->where('usage_summary.draft_allocation_count', 1)
                    ->where(
                        'usage_summary.published_allocation_count',
                        1
                    )
                    ->where('usage_summary.distribution_count', 3)
                    ->missing('can_delete')
                    ->missing('delete_url')
            );
    }

    public function test_company_detail_and_edit_have_no_investor_or_reassignment_options(): void
    {
        $recipient = $this->recipient(null, [
            'type' => ProfitRecipientType::Company,
        ]);
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('profit-recipients.show', $recipient))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('recipient.type', 'company')
                    ->where('investor', null)
            );
        $this->actingAs($user)
            ->get(route('profit-recipients.edit', $recipient))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('recipient.type', 'company')
                    ->where('investor', null)
                    ->missing('investors')
                    ->missing('type_options')
            );
    }

    public function test_detail_query_count_is_bounded_and_gets_do_not_mutate(): void
    {
        $recipient = $this->recipient();
        ProfitSchemeAllocation::factory()
            ->count(10)
            ->for($recipient, 'recipient')
            ->create();
        ProfitDistribution::factory()
            ->count(10)
            ->for($recipient, 'recipient')
            ->create();
        $before = DB::table('profit_recipients')
            ->where('id', $recipient->id)
            ->first();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->user())
            ->get(route('profit-recipients.show', $recipient))
            ->assertOk();
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(8, $queryCount);
        $this->assertEquals(
            $before,
            DB::table('profit_recipients')
                ->where('id', $recipient->id)
                ->first()
        );
    }
}
