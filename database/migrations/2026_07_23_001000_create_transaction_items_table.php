<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id');
            $table->foreignId('product_id')->nullable();
            $table->foreignId('product_category_id')->nullable();
            $table->foreignId('applied_profit_scheme_version_id')->nullable();
            $table->string('product_name_snapshot', 160);
            $table->string('product_sku_snapshot', 80);
            $table->string('category_name_snapshot', 120)->nullable();
            $table->integer('quantity');
            $table->decimal('unit_selling_price', 18, 2);
            $table->decimal('unit_production_cost', 18, 2);
            $table->decimal('total_revenue', 18, 2);
            $table->decimal('total_production_cost', 18, 2);
            $table->decimal('net_profit', 18, 2);
            $table->timestamp('rule_resolved_at')->nullable();
            $table->timestamps();

            $table->index('transaction_id', 'idx_transaction_items_transaction');
            $table->index('product_id', 'idx_transaction_items_product');
            $table->index('product_category_id', 'idx_transaction_items_category');
            $table->index(
                'applied_profit_scheme_version_id',
                'idx_transaction_items_applied_rule'
            );
            $table->foreign('transaction_id', 'fk_transaction_items_transaction')
                ->references('id')
                ->on('transactions')
                ->restrictOnDelete();
            $table->foreign('product_id', 'fk_transaction_items_product')
                ->references('id')
                ->on('products')
                ->nullOnDelete();
            $table->foreign('product_category_id', 'fk_transaction_items_category')
                ->references('id')
                ->on('product_categories')
                ->nullOnDelete();
            $table->foreign(
                'applied_profit_scheme_version_id',
                'fk_transaction_items_applied_rule'
            )
                ->references('id')
                ->on('profit_scheme_versions')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE transaction_items ADD CONSTRAINT chk_transaction_items_quantity_positive '
            .'CHECK (quantity > 0)'
        );
        DB::statement(
            'ALTER TABLE transaction_items ADD CONSTRAINT '
            .'chk_transaction_items_unit_selling_price_nonnegative '
            .'CHECK (unit_selling_price >= 0)'
        );
        DB::statement(
            'ALTER TABLE transaction_items ADD CONSTRAINT '
            .'chk_transaction_items_unit_production_cost_nonnegative '
            .'CHECK (unit_production_cost >= 0)'
        );
        DB::statement(
            'ALTER TABLE transaction_items ADD CONSTRAINT '
            .'chk_transaction_items_total_revenue_nonnegative '
            .'CHECK (total_revenue >= 0)'
        );
        DB::statement(
            'ALTER TABLE transaction_items ADD CONSTRAINT '
            .'chk_transaction_items_total_production_cost_nonnegative '
            .'CHECK (total_production_cost >= 0)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE transaction_items DROP CONSTRAINT IF EXISTS '
            .'chk_transaction_items_total_production_cost_nonnegative'
        );
        DB::statement(
            'ALTER TABLE transaction_items DROP CONSTRAINT IF EXISTS '
            .'chk_transaction_items_total_revenue_nonnegative'
        );
        DB::statement(
            'ALTER TABLE transaction_items DROP CONSTRAINT IF EXISTS '
            .'chk_transaction_items_unit_production_cost_nonnegative'
        );
        DB::statement(
            'ALTER TABLE transaction_items DROP CONSTRAINT IF EXISTS '
            .'chk_transaction_items_unit_selling_price_nonnegative'
        );
        DB::statement(
            'ALTER TABLE transaction_items DROP CONSTRAINT IF EXISTS '
            .'chk_transaction_items_quantity_positive'
        );

        Schema::dropIfExists('transaction_items');
    }
};
