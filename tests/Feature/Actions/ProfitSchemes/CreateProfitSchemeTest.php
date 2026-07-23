<?php

namespace Tests\Feature\Actions\ProfitSchemes;

use App\Actions\ProfitSchemes\CreateProfitScheme;
use App\Exceptions\ProfitSchemes\InvalidProfitScheme;
use App\Models\User;

class CreateProfitSchemeTest extends ProfitSchemeActionTestCase
{
    public function test_scheme_is_created_with_creator_audit_field(): void
    {
        $creator = User::factory()->create();

        $scheme = app(CreateProfitScheme::class)->execute([
            'name' => 'Growth Sharing',
            'code' => 'growth-sharing',
            'description' => 'Growth allocation.',
            'is_active' => true,
        ], $creator);

        $this->assertSame($creator->id, $scheme->created_by_user_id);
        $this->assertSame('Growth Sharing', $scheme->name);
        $this->assertTrue($scheme->is_active);
    }

    public function test_code_normalization_is_deterministic_lowercase_kebab_case(): void
    {
        $creator = User::factory()->create();

        $scheme = app(CreateProfitScheme::class)->execute([
            'name' => 'Seasonal Growth',
            'code' => '  Seasonal GROWTH 2026  ',
            'description' => null,
            'is_active' => true,
        ], $creator);

        $this->assertSame('seasonal-growth-2026', $scheme->code);
    }

    public function test_duplicate_normalized_code_is_rejected(): void
    {
        $creator = User::factory()->create();
        $action = app(CreateProfitScheme::class);
        $action->execute([
            'name' => 'First',
            'code' => 'Seasonal Growth',
            'is_active' => true,
        ], $creator);

        $this->expectException(InvalidProfitScheme::class);
        $this->expectExceptionMessage('already exists');

        $action->execute([
            'name' => 'Duplicate',
            'code' => 'seasonal-growth',
            'is_active' => true,
        ], $creator);
    }

    public function test_scheme_creation_does_not_create_or_publish_a_version(): void
    {
        $creator = User::factory()->create();

        $scheme = app(CreateProfitScheme::class)->execute([
            'name' => 'Draftless Scheme',
            'code' => 'draftless-scheme',
            'is_active' => true,
        ], $creator);

        $this->assertDatabaseCount('profit_scheme_versions', 0);
        $this->assertFalse($scheme->versions()->exists());
    }
}
