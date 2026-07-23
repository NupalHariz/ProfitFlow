<?php

namespace Tests\Feature\Actions\ProfitSchemes;

use App\Actions\ProfitSchemes\CreateProfitSchemeVersion;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeVersion;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class CreateProfitSchemeVersionTest extends ProfitSchemeActionTestCase
{
    public function test_first_version_receives_version_number_one(): void
    {
        $version = $this->createDraftVersion();

        $this->assertSame(1, $version->version);
    }

    public function test_subsequent_version_receives_current_maximum_plus_one(): void
    {
        $creator = User::factory()->create();
        $scheme = ProfitScheme::factory()
            ->for($creator, 'creator')
            ->create();
        $this->createDraftVersion($scheme, $creator);

        $second = $this->createDraftVersion($scheme, $creator);

        $this->assertSame(2, $second->version);
    }

    public function test_version_numbers_are_scoped_per_scheme(): void
    {
        $creator = User::factory()->create();
        $firstScheme = ProfitScheme::factory()
            ->for($creator, 'creator')
            ->create();
        $secondScheme = ProfitScheme::factory()
            ->for($creator, 'creator')
            ->create();

        $first = $this->createDraftVersion($firstScheme, $creator);
        $second = $this->createDraftVersion($secondScheme, $creator);

        $this->assertSame(1, $first->version);
        $this->assertSame(1, $second->version);
    }

    public function test_new_version_is_unpublished(): void
    {
        $version = $this->createDraftVersion();

        $this->assertNull($version->published_at);
    }

    public function test_version_creator_audit_field_is_stored(): void
    {
        $creator = User::factory()->create();

        $version = $this->createDraftVersion(creator: $creator);

        $this->assertSame($creator->id, $version->created_by_user_id);
    }

    public function test_nullable_category_and_open_profit_ranges_are_supported(): void
    {
        $version = $this->createDraftVersion(overrides: [
            'product_category_id' => null,
            'minimum_profit' => null,
            'maximum_profit' => null,
        ]);

        $this->assertNull($version->product_category_id);
        $this->assertNull($version->minimum_profit);
        $this->assertNull($version->maximum_profit);
    }

    public function test_invalid_date_range_is_rejected_before_insert(): void
    {
        try {
            $this->createDraftVersion(overrides: [
                'effective_from' => '2026-07-15',
                'effective_until' => '2026-07-14',
            ]);
            $this->fail('Expected an invalid date-range exception.');
        } catch (InvalidProfitSchemeVersion $exception) {
            $this->assertStringContainsString(
                'Effective until',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('profit_scheme_versions', 0);
    }

    public function test_invalid_profit_range_is_rejected_before_insert(): void
    {
        try {
            $this->createDraftVersion(overrides: [
                'minimum_profit' => '100.01',
                'maximum_profit' => '100.00',
            ]);
            $this->fail('Expected an invalid profit-range exception.');
        } catch (InvalidProfitSchemeVersion $exception) {
            $this->assertStringContainsString(
                'Minimum profit',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('profit_scheme_versions', 0);
    }

    public function test_nonexistent_category_is_rejected(): void
    {
        $this->expectException(InvalidProfitSchemeVersion::class);
        $this->expectExceptionMessage('existing category');

        $this->createDraftVersion(overrides: [
            'product_category_id' => 999_999,
        ]);
    }

    public function test_malformed_effective_date_is_rejected(): void
    {
        $this->expectException(InvalidProfitSchemeVersion::class);
        $this->expectExceptionMessage('valid YYYY-MM-DD date');

        $this->createDraftVersion(overrides: [
            'effective_from' => '2026-02-30',
        ]);
    }

    #[DataProvider('invalidScalarRuleFields')]
    public function test_invalid_priority_or_activity_type_is_rejected(
        string $field,
        mixed $value,
        string $message
    ): void {
        $this->expectException(InvalidProfitSchemeVersion::class);
        $this->expectExceptionMessage($message);

        $this->createDraftVersion(overrides: [$field => $value]);
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidScalarRuleFields(): array
    {
        return [
            'numeric-string priority' => [
                'priority',
                '10',
                'must be an integer',
            ],
            'integer activity flag' => [
                'is_active',
                1,
                'must be a boolean',
            ],
        ];
    }

    #[DataProvider('malformedMoneyValues')]
    public function test_malformed_decimal_value_is_rejected(string $value): void
    {
        $this->expectException(InvalidProfitSchemeVersion::class);
        $this->expectExceptionMessage('decimal(18,2)');

        $this->createDraftVersion(overrides: [
            'minimum_profit' => $value,
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedMoneyValues(): array
    {
        return [
            'alphabetic' => ['invalid'],
            'excess precision' => ['1.001'],
            'scientific notation' => ['1e2'],
            'outside decimal range' => ['10000000000000000.00'],
        ];
    }

    public function test_parent_scheme_row_is_locked_for_version_numbering(): void
    {
        $creator = User::factory()->create();
        $scheme = ProfitScheme::factory()
            ->for($creator, 'creator')
            ->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(CreateProfitSchemeVersion::class)->execute(
            $scheme,
            $this->validVersionData(),
            $creator
        );
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $schemeLockQueries = array_filter(
            $queries,
            static fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "profit_schemes"'
            ) && str_contains(strtolower($query['query']), 'for update')
        );

        $this->assertCount(1, $schemeLockQueries);
    }

    public function test_unique_scheme_version_constraint_is_second_safety_layer(): void
    {
        $scheme = ProfitScheme::factory()->create();
        ProfitSchemeVersion::factory()
            ->for($scheme, 'scheme')
            ->create(['version' => 1]);

        $this->expectException(QueryException::class);

        ProfitSchemeVersion::factory()
            ->for($scheme, 'scheme')
            ->create(['version' => 1]);
    }
}
