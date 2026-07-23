<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_scheme_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profit_scheme_version_id');
            $table->foreignId('profit_recipient_id');
            $table->decimal('percentage', 7, 4);
            $table->timestamps();

            $table->unique(
                ['profit_scheme_version_id', 'profit_recipient_id'],
                'uq_scheme_allocation_recipient'
            );
            $table->index('profit_recipient_id', 'idx_scheme_allocations_recipient');
            $table->foreign('profit_scheme_version_id', 'fk_scheme_allocations_version')
                ->references('id')
                ->on('profit_scheme_versions')
                ->cascadeOnDelete();
            $table->foreign('profit_recipient_id', 'fk_scheme_allocations_recipient')
                ->references('id')
                ->on('profit_recipients')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE profit_scheme_allocations ADD CONSTRAINT '
            .'chk_scheme_allocations_percentage_range '
            .'CHECK (percentage > 0 AND percentage <= 100.0000)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE profit_scheme_allocations DROP CONSTRAINT IF EXISTS '
            .'chk_scheme_allocations_percentage_range'
        );

        Schema::dropIfExists('profit_scheme_allocations');
    }
};
