<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_domain_tables_exist_after_migration(): void
    {
        $tables = [
            'product_categories',
            'products',
            'customers',
            'investors',
            'profit_recipients',
            'profit_schemes',
            'profit_scheme_versions',
            'profit_scheme_allocations',
            'transactions',
            'transaction_items',
            'profit_distributions',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }
    }

    public function test_users_name_column_matches_the_authoritative_length(): void
    {
        $length = DB::scalar(<<<'SQL'
            SELECT character_maximum_length
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'users'
              AND column_name = 'name'
        SQL);

        $this->assertSame(150, (int) $length);
    }
}
