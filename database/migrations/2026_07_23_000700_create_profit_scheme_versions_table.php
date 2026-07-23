<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_scheme_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profit_scheme_id');
            $table->integer('version');
            $table->foreignId('product_category_id')->nullable();
            $table->decimal('minimum_profit', 18, 2)->nullable();
            $table->decimal('maximum_profit', 18, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by_user_id');
            $table->timestamps();

            $table->unique(
                ['profit_scheme_id', 'version'],
                'uq_profit_scheme_versions_scheme_version'
            );
            $table->index(
                ['is_active', 'product_category_id', 'effective_from', 'priority'],
                'idx_rule_resolution'
            );
            $table->index(
                ['effective_from', 'effective_until'],
                'idx_rule_effective_period'
            );
            $table->foreign('profit_scheme_id', 'fk_profit_scheme_versions_scheme')
                ->references('id')
                ->on('profit_schemes')
                ->restrictOnDelete();
            $table->foreign('product_category_id', 'fk_profit_scheme_versions_category')
                ->references('id')
                ->on('product_categories')
                ->restrictOnDelete();
            $table->foreign('created_by_user_id', 'fk_profit_scheme_versions_creator')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE profit_scheme_versions ADD CONSTRAINT chk_profit_scheme_versions_version_positive '
            .'CHECK (version > 0)'
        );
        DB::statement(
            'ALTER TABLE profit_scheme_versions ADD CONSTRAINT chk_profit_scheme_versions_profit_range '
            .'CHECK (minimum_profit IS NULL OR maximum_profit IS NULL '
            .'OR minimum_profit <= maximum_profit)'
        );
        DB::statement(
            'ALTER TABLE profit_scheme_versions ADD CONSTRAINT chk_profit_scheme_versions_effective_period '
            .'CHECK (effective_until IS NULL OR effective_until >= effective_from)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE profit_scheme_versions DROP CONSTRAINT IF EXISTS '
            .'chk_profit_scheme_versions_effective_period'
        );
        DB::statement(
            'ALTER TABLE profit_scheme_versions DROP CONSTRAINT IF EXISTS '
            .'chk_profit_scheme_versions_profit_range'
        );
        DB::statement(
            'ALTER TABLE profit_scheme_versions DROP CONSTRAINT IF EXISTS '
            .'chk_profit_scheme_versions_version_positive'
        );

        Schema::dropIfExists('profit_scheme_versions');
    }
};
