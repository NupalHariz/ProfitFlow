<?php

namespace Tests\Feature\Actions\ProfitSchemes;

use App\Actions\ProfitSchemes\UpdateDraftProfitSchemeVersion;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeVersion;
use App\Exceptions\ProfitSchemes\ProfitSchemeVersionImmutable;
use App\Models\ProductCategory;
use PHPUnit\Framework\Attributes\DataProvider;

class UpdateDraftProfitSchemeVersionTest extends ProfitSchemeActionTestCase
{
    public function test_draft_rule_fields_can_be_updated_and_normalized(): void
    {
        $category = ProductCategory::factory()->create();
        $version = $this->createDraftVersion();

        $updated = app(UpdateDraftProfitSchemeVersion::class)->execute(
            $version,
            [
                'product_category_id' => $category->id,
                'minimum_profit' => '1',
                'maximum_profit' => '999.9',
                'effective_from' => '2026-07-01',
                'effective_until' => '2026-12-31',
                'priority' => -5,
                'is_active' => false,
            ]
        );

        $this->assertSame($category->id, $updated->product_category_id);
        $this->assertSame('1.00', $updated->minimum_profit);
        $this->assertSame('999.90', $updated->maximum_profit);
        $this->assertSame(
            '2026-07-01',
            $updated->effective_from->toDateString()
        );
        $this->assertSame(
            '2026-12-31',
            $updated->effective_until->toDateString()
        );
        $this->assertSame(-5, $updated->priority);
        $this->assertFalse($updated->is_active);
    }

    #[DataProvider('protectedIdentityFields')]
    public function test_protected_identity_field_cannot_be_changed(
        string $field,
        mixed $value
    ): void {
        $version = $this->createDraftVersion();
        $original = $version->getAttributes();

        try {
            app(UpdateDraftProfitSchemeVersion::class)->execute(
                $version,
                [$field => $value]
            );
            $this->fail('Expected protected-field validation to fail.');
        } catch (InvalidProfitSchemeVersion $exception) {
            $this->assertStringContainsString(
                'protected',
                $exception->getMessage()
            );
        }

        $fresh = $version->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(
            $original['profit_scheme_id'],
            $fresh->getRawOriginal('profit_scheme_id')
        );
        $this->assertSame(
            $original['version'],
            $fresh->getRawOriginal('version')
        );
        $this->assertSame(
            $original['created_by_user_id'],
            $fresh->getRawOriginal('created_by_user_id')
        );
        $this->assertSame(
            $original['published_at'],
            $fresh->getRawOriginal('published_at')
        );
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function protectedIdentityFields(): array
    {
        return [
            'scheme identity' => ['profit_scheme_id', 999],
            'version number' => ['version', 999],
            'creator audit field' => ['created_by_user_id', 999],
            'publication timestamp' => [
                'published_at',
                '2026-07-15 00:00:00',
            ],
        ];
    }

    public function test_published_version_update_is_rejected(): void
    {
        $version = $this->createDraftVersion();
        $recipient = $this->createRecipient();
        $this->replaceAllocations($version, [[
            'profit_recipient_id' => $recipient->id,
            'percentage' => '100.0000',
        ]]);
        $published = $this->publish($version);

        $this->expectException(ProfitSchemeVersionImmutable::class);
        $this->expectExceptionMessage('Published');

        app(UpdateDraftProfitSchemeVersion::class)->execute(
            $published,
            ['priority' => 999]
        );
    }

    public function test_historically_referenced_version_update_is_rejected(): void
    {
        $version = $this->createDraftVersion();
        $this->referenceVersion($version);

        $this->expectException(ProfitSchemeVersionImmutable::class);
        $this->expectExceptionMessage('Historically referenced');

        app(UpdateDraftProfitSchemeVersion::class)->execute(
            $version,
            ['priority' => 999]
        );
    }

    public function test_rejected_update_leaves_previous_values_unchanged(): void
    {
        $version = $this->createDraftVersion(overrides: [
            'minimum_profit' => '10.00',
            'maximum_profit' => '100.00',
            'priority' => 7,
        ]);
        $originalUpdatedAt = $version->updated_at;

        try {
            app(UpdateDraftProfitSchemeVersion::class)->execute(
                $version,
                [
                    'product_category_id' => $version
                        ->product_category_id,
                    'minimum_profit' => '101.00',
                    'priority' => 99,
                ]
            );
            $this->fail('Expected an invalid update.');
        } catch (InvalidProfitSchemeVersion) {
            // Expected.
        }

        $fresh = $version->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame('10.00', $fresh->minimum_profit);
        $this->assertSame('100.00', $fresh->maximum_profit);
        $this->assertSame(7, $fresh->priority);
        $this->assertTrue($originalUpdatedAt->equalTo($fresh->updated_at));
    }

    public function test_draft_update_requires_category_input(): void
    {
        $version = $this->createDraftVersion();

        $this->expectException(InvalidProfitSchemeVersion::class);
        $this->expectExceptionMessage(
            'field [product_category_id] is required'
        );

        app(UpdateDraftProfitSchemeVersion::class)->execute(
            $version,
            ['priority' => 99]
        );
    }

    public function test_draft_update_rejects_null_category(): void
    {
        $version = $this->createDraftVersion();

        $this->expectException(InvalidProfitSchemeVersion::class);
        $this->expectExceptionMessage('Product category is required');

        app(UpdateDraftProfitSchemeVersion::class)->execute(
            $version,
            ['product_category_id' => null]
        );
    }
}
