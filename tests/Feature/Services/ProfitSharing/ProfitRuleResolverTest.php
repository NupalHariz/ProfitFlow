<?php

namespace Tests\Feature\Services\ProfitSharing;

use App\Models\ProductCategory;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use App\Services\ProfitSharing\ProfitRuleResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfitRuleResolverTest extends TestCase
{
    use RefreshDatabase;

    private const RESOLUTION_DATE = '2026-07-15';

    private ProfitRuleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ProfitRuleResolver;
    }

    public function test_exact_category_rule_matches(): void
    {
        $category = ProductCategory::factory()->create();
        $rule = $this->createRule([
            'product_category_id' => $category->id,
        ]);

        $this->assertRuleResolved($rule, $category->id);
    }

    public function test_general_rule_matches_when_no_category_rule_matches(): void
    {
        $requestedCategory = ProductCategory::factory()->create();
        $otherCategory = ProductCategory::factory()->create();
        $generalRule = $this->createRule();
        $this->createRule([
            'product_category_id' => $otherCategory->id,
            'priority' => 100,
        ]);

        $this->assertRuleResolved($generalRule, $requestedCategory->id);
    }

    public function test_general_rule_matches_a_supplied_category(): void
    {
        $category = ProductCategory::factory()->create();
        $generalRule = $this->createRule([
            'product_category_id' => null,
        ]);

        $this->assertRuleResolved($generalRule, $category->id);
    }

    public function test_only_general_rules_match_when_category_is_null(): void
    {
        $category = ProductCategory::factory()->create();
        $generalRule = $this->createRule();
        $this->createRule([
            'product_category_id' => $category->id,
            'priority' => 100,
        ]);

        $this->assertRuleResolved($generalRule, null);
    }

    public function test_inactive_version_is_ignored(): void
    {
        $this->createRule(['is_active' => false]);

        $this->assertNull($this->resolve());
    }

    public function test_inactive_parent_scheme_is_ignored(): void
    {
        $scheme = ProfitScheme::factory()->create(['is_active' => false]);
        $this->createRule(scheme: $scheme);

        $this->assertNull($this->resolve());
    }

    public function test_unpublished_version_is_ignored(): void
    {
        $this->createRule(['published_at' => null]);

        $this->assertNull($this->resolve());
    }

    public function test_effective_start_boundary_is_inclusive(): void
    {
        $rule = $this->createRule([
            'effective_from' => self::RESOLUTION_DATE,
        ]);

        $this->assertRuleResolved($rule);
    }

    public function test_effective_end_boundary_is_inclusive(): void
    {
        $rule = $this->createRule([
            'effective_until' => self::RESOLUTION_DATE,
        ]);

        $this->assertRuleResolved($rule);
    }

    public function test_version_before_its_effective_period_is_ignored(): void
    {
        $this->createRule([
            'effective_from' => '2026-07-16',
        ]);

        $this->assertNull($this->resolve());
    }

    public function test_version_after_its_effective_period_is_ignored(): void
    {
        $this->createRule([
            'effective_until' => '2026-07-14',
        ]);

        $this->assertNull($this->resolve());
    }

    public function test_null_effective_end_is_open_ended(): void
    {
        $rule = $this->createRule([
            'effective_from' => '2020-01-01',
            'effective_until' => null,
        ]);

        $this->assertRuleResolved($rule, date: '2040-12-31');
    }

    public function test_minimum_profit_boundary_is_inclusive(): void
    {
        $rule = $this->createRule([
            'minimum_profit' => '100.00',
        ]);

        $this->assertRuleResolved($rule, profit: '100.00');
    }

    public function test_maximum_profit_boundary_is_inclusive(): void
    {
        $rule = $this->createRule([
            'maximum_profit' => '100.00',
        ]);

        $this->assertRuleResolved($rule, profit: '100.00');
    }

    public function test_null_minimum_profit_is_open_ended(): void
    {
        $rule = $this->createRule([
            'minimum_profit' => null,
            'maximum_profit' => '-100.00',
        ]);

        $this->assertRuleResolved($rule, profit: '-999999.99');
    }

    public function test_null_maximum_profit_is_open_ended(): void
    {
        $rule = $this->createRule([
            'minimum_profit' => '100.00',
            'maximum_profit' => null,
        ]);

        $this->assertRuleResolved(
            $rule,
            profit: '9999999999999999.99'
        );
    }

    public function test_profit_below_minimum_is_ignored(): void
    {
        $this->createRule([
            'minimum_profit' => '100.00',
        ]);

        $this->assertNull($this->resolve(profit: '99.99'));
    }

    public function test_profit_above_maximum_is_ignored(): void
    {
        $this->createRule([
            'maximum_profit' => '100.00',
        ]);

        $this->assertNull($this->resolve(profit: '100.01'));
    }

    public function test_highest_priority_matching_rule_wins(): void
    {
        $this->createRule(['priority' => 10]);
        $highestPriority = $this->createRule(['priority' => 20]);

        $this->assertRuleResolved($highestPriority);
    }

    public function test_higher_priority_general_rule_beats_category_rule(): void
    {
        $category = ProductCategory::factory()->create();
        $this->createRule([
            'product_category_id' => $category->id,
            'priority' => 10,
        ]);
        $generalRule = $this->createRule([
            'product_category_id' => null,
            'priority' => 20,
        ]);

        $this->assertRuleResolved($generalRule, $category->id);
    }

    public function test_category_rule_has_no_hidden_precedence(): void
    {
        $category = ProductCategory::factory()->create();
        $this->createRule([
            'product_category_id' => $category->id,
            'priority' => 10,
            'published_at' => '2026-07-01 00:00:00',
        ]);
        $newerGeneralRule = $this->createRule([
            'product_category_id' => null,
            'priority' => 10,
            'published_at' => '2026-07-02 00:00:00',
        ]);

        $this->assertRuleResolved($newerGeneralRule, $category->id);
    }

    public function test_equal_priority_chooses_latest_publication(): void
    {
        $this->createRule([
            'priority' => 10,
            'published_at' => '2026-07-01 00:00:00',
        ]);
        $latest = $this->createRule([
            'priority' => 10,
            'published_at' => '2026-07-02 00:00:00',
        ]);

        $this->assertRuleResolved($latest);
    }

    public function test_equal_priority_and_publication_chooses_highest_version(): void
    {
        $scheme = ProfitScheme::factory()->create();
        $publication = '2026-07-01 00:00:00';
        $this->createRule([
            'version' => 1,
            'priority' => 10,
            'published_at' => $publication,
        ], $scheme);
        $highestVersion = $this->createRule([
            'version' => 2,
            'priority' => 10,
            'published_at' => $publication,
        ], $scheme);

        $this->assertRuleResolved($highestVersion);
    }

    public function test_complete_tie_chooses_highest_id(): void
    {
        $attributes = [
            'version' => 1,
            'priority' => 10,
            'published_at' => '2026-07-01 00:00:00',
        ];
        $this->createRule($attributes);
        $highestId = $this->createRule($attributes);

        $this->assertRuleResolved($highestId);
    }

    public function test_rule_from_another_category_is_ignored(): void
    {
        $requestedCategory = ProductCategory::factory()->create();
        $otherCategory = ProductCategory::factory()->create();
        $this->createRule([
            'product_category_id' => $otherCategory->id,
        ]);

        $this->assertNull($this->resolve($requestedCategory->id));
    }

    public function test_no_applicable_rule_returns_null(): void
    {
        $this->assertNull($this->resolve());
    }

    #[DataProvider('malformedProfits')]
    public function test_malformed_profit_is_rejected(string $profit): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolve(profit: $profit);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedProfits(): array
    {
        return [
            'alphabetic' => ['not-money'],
            'scientific notation' => ['1e2'],
            'leading whitespace' => [' 100.00'],
            'comma separator' => ['100,00'],
        ];
    }

    public function test_excess_profit_precision_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolve(profit: '100.001');
    }

    #[DataProvider('outOfRangeProfits')]
    public function test_profit_outside_decimal_column_range_is_rejected(
        string $profit
    ): void {
        $this->expectException(InvalidArgumentException::class);

        $this->resolve(profit: $profit);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function outOfRangeProfits(): array
    {
        return [
            'positive overflow' => ['10000000000000000.00'],
            'negative overflow' => ['-10000000000000000.00'],
        ];
    }

    #[DataProvider('nonPositiveProfits')]
    public function test_zero_and_negative_profit_can_match_configured_ranges(
        string $profit
    ): void {
        $rule = $this->createRule([
            'minimum_profit' => '-100.00',
            'maximum_profit' => '0.00',
        ]);

        $this->assertRuleResolved($rule, profit: $profit);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonPositiveProfits(): array
    {
        return [
            'zero' => ['0.00'],
            'negative' => ['-50.25'],
        ];
    }

    public function test_carbon_transaction_date_is_supported(): void
    {
        $rule = $this->createRule([
            'effective_from' => self::RESOLUTION_DATE,
            'effective_until' => self::RESOLUTION_DATE,
        ]);

        $this->assertRuleResolved(
            $rule,
            date: CarbonImmutable::parse(self::RESOLUTION_DATE)
        );
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_transaction_date_is_rejected(string $date): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolve(date: $date);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDates(): array
    {
        return [
            'calendar overflow' => ['2026-02-30'],
            'datetime instead of date' => ['2026-07-15 00:00:00'],
            'non-ISO form' => ['15-07-2026'],
        ];
    }

    public function test_resolver_executes_one_bounded_query_without_allocations(): void
    {
        $rule = $this->createRule();
        $this->createRule(['priority' => -10]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $resolved = $this->resolve(profit: '100');
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertNotNull($resolved);
        $this->assertSame($rule->id, $resolved->id);
        $this->assertCount(1, $queries);

        $sql = strtolower($queries[0]['query']);

        $this->assertStringContainsString('exists', $sql);
        $this->assertStringContainsString('limit 1', $sql);
        $this->assertStringContainsString(
            'order by "priority" desc, "published_at" desc, "version" desc, "id" desc',
            $sql
        );
        $this->assertStringNotContainsString(
            'profit_scheme_allocations',
            $sql
        );
        $this->assertSame(
            ['100.00', '100.00'],
            array_slice($queries[0]['bindings'], -2)
        );
        $this->assertFalse($resolved->relationLoaded('allocations'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRule(
        array $attributes = [],
        ?ProfitScheme $scheme = null
    ): ProfitSchemeVersion {
        $scheme ??= ProfitScheme::factory()->create();

        $rule = ProfitSchemeVersion::factory()
            ->for($scheme, 'scheme')
            ->published()
            ->create(array_merge([
                'minimum_profit' => null,
                'maximum_profit' => null,
                'effective_from' => '2026-01-01',
                'effective_until' => null,
                'priority' => 0,
                'is_active' => true,
            ], $attributes));

        ProfitSchemeAllocation::factory()
            ->for($rule, 'version')
            ->create(['percentage' => '100.0000']);

        return $rule;
    }

    private function resolve(
        ?int $categoryId = null,
        string $profit = '100.00',
        CarbonInterface|string $date = self::RESOLUTION_DATE,
    ): ?ProfitSchemeVersion {
        return $this->resolver->resolve($date, $categoryId, $profit);
    }

    private function assertRuleResolved(
        ProfitSchemeVersion $expected,
        ?int $categoryId = null,
        string $profit = '100.00',
        CarbonInterface|string $date = self::RESOLUTION_DATE,
    ): void {
        $resolved = $this->resolve($categoryId, $profit, $date);

        $this->assertNotNull($resolved);
        $this->assertSame($expected->id, $resolved->id);
    }
}
