<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 40);
            $table->uuid('idempotency_key')->nullable();
            $table->foreignId('customer_id');
            $table->date('transaction_date');
            $table->string('status', 32)->default('draft');
            $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_production_cost', 18, 2)->default(0);
            $table->decimal('total_net_profit', 18, 2)->default(0);
            $table->text('failure_reason')->nullable();
            $table->foreignId('created_by_user_id');
            $table->foreignId('completed_by_user_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('distribution_started_at')->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique('transaction_number', 'uq_transactions_transaction_number');
            $table->unique('idempotency_key', 'uq_transactions_idempotency_key');
            $table->index(
                ['status', 'transaction_date', 'id'],
                'idx_transactions_status_date_id'
            );
            $table->index(
                ['customer_id', 'transaction_date'],
                'idx_transactions_customer_date'
            );
            $table->index(['transaction_date', 'id'], 'idx_transactions_date_id');
            $table->foreign('customer_id', 'fk_transactions_customer')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign('created_by_user_id', 'fk_transactions_creator')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('completed_by_user_id', 'fk_transactions_completer')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE transactions ADD CONSTRAINT chk_transactions_status '
            ."CHECK (status IN ('draft', 'pending_distribution', 'processing_distribution', "
            ."'distributed', 'distribution_failed', 'cancelled'))"
        );
        DB::statement(
            'ALTER TABLE transactions ADD CONSTRAINT chk_transactions_total_revenue_nonnegative '
            .'CHECK (total_revenue >= 0)'
        );
        DB::statement(
            'ALTER TABLE transactions ADD CONSTRAINT '
            .'chk_transactions_total_production_cost_nonnegative '
            .'CHECK (total_production_cost >= 0)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE transactions DROP CONSTRAINT IF EXISTS '
            .'chk_transactions_total_production_cost_nonnegative'
        );
        DB::statement(
            'ALTER TABLE transactions DROP CONSTRAINT IF EXISTS '
            .'chk_transactions_total_revenue_nonnegative'
        );
        DB::statement(
            'ALTER TABLE transactions DROP CONSTRAINT IF EXISTS chk_transactions_status'
        );

        Schema::dropIfExists('transactions');
    }
};
