<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id');
            $table->foreignId('transaction_item_id');
            $table->foreignId('profit_scheme_version_id');
            $table->foreignId('profit_recipient_id');
            $table->string('recipient_name_snapshot', 160);
            $table->decimal('percentage', 7, 4);
            $table->decimal('amount', 18, 2);
            $table->timestamp('created_at')->nullable();

            $table->unique(
                ['transaction_item_id', 'profit_recipient_id'],
                'uq_item_recipient_distribution'
            );
            $table->index(
                ['transaction_id', 'created_at'],
                'idx_distributions_transaction_date'
            );
            $table->index(
                ['profit_recipient_id', 'created_at'],
                'idx_distributions_recipient_date'
            );
            $table->index(
                'profit_scheme_version_id',
                'idx_distributions_rule_version'
            );
            $table->foreign('transaction_id', 'fk_distributions_transaction')
                ->references('id')
                ->on('transactions')
                ->restrictOnDelete();
            $table->foreign('transaction_item_id', 'fk_distributions_transaction_item')
                ->references('id')
                ->on('transaction_items')
                ->restrictOnDelete();
            $table->foreign('profit_scheme_version_id', 'fk_distributions_rule_version')
                ->references('id')
                ->on('profit_scheme_versions')
                ->restrictOnDelete();
            $table->foreign('profit_recipient_id', 'fk_distributions_recipient')
                ->references('id')
                ->on('profit_recipients')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE profit_distributions ADD CONSTRAINT '
            .'chk_profit_distributions_percentage_range '
            .'CHECK (percentage > 0 AND percentage <= 100.0000)'
        );
        DB::statement(
            'ALTER TABLE profit_distributions ADD CONSTRAINT '
            .'chk_profit_distributions_amount_nonnegative '
            .'CHECK (amount >= 0)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE profit_distributions DROP CONSTRAINT IF EXISTS '
            .'chk_profit_distributions_amount_nonnegative'
        );
        DB::statement(
            'ALTER TABLE profit_distributions DROP CONSTRAINT IF EXISTS '
            .'chk_profit_distributions_percentage_range'
        );

        Schema::dropIfExists('profit_distributions');
    }
};
