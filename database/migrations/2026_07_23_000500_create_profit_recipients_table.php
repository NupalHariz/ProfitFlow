<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80);
            $table->string('type', 16);
            $table->foreignId('investor_id')->nullable();
            $table->string('display_name', 160);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code', 'uq_profit_recipients_code');
            $table->unique('investor_id', 'uq_profit_recipients_investor');
            $table->index(['type', 'is_active'], 'idx_profit_recipients_type_active');
            $table->foreign('investor_id', 'fk_profit_recipients_investor')
                ->references('id')
                ->on('investors')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE profit_recipients ADD CONSTRAINT chk_profit_recipients_type '
            ."CHECK (type IN ('investor', 'company'))"
        );
        DB::statement(
            'ALTER TABLE profit_recipients ADD CONSTRAINT chk_profit_recipients_investor_assignment '
            ."CHECK ((type = 'investor' AND investor_id IS NOT NULL) "
            ."OR (type = 'company' AND investor_id IS NULL))"
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE profit_recipients DROP CONSTRAINT IF EXISTS '
            .'chk_profit_recipients_investor_assignment'
        );
        DB::statement(
            'ALTER TABLE profit_recipients DROP CONSTRAINT IF EXISTS chk_profit_recipients_type'
        );

        Schema::dropIfExists('profit_recipients');
    }
};
