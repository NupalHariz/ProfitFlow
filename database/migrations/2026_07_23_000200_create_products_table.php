<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id');
            $table->string('name', 160);
            $table->string('sku', 80);
            $table->text('description')->nullable();
            $table->decimal('default_selling_price', 18, 2);
            $table->decimal('default_production_cost', 18, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('sku', 'uq_products_sku');
            $table->index('product_category_id', 'idx_products_category');
            $table->index(['is_active', 'name'], 'idx_products_active_name');
            $table->foreign('product_category_id', 'fk_products_category')
                ->references('id')
                ->on('product_categories')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE products ADD CONSTRAINT chk_products_default_selling_price_nonnegative '
            .'CHECK (default_selling_price >= 0)'
        );
        DB::statement(
            'ALTER TABLE products ADD CONSTRAINT chk_products_default_production_cost_nonnegative '
            .'CHECK (default_production_cost >= 0)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE products DROP CONSTRAINT IF EXISTS chk_products_default_selling_price_nonnegative'
        );
        DB::statement(
            'ALTER TABLE products DROP CONSTRAINT IF EXISTS chk_products_default_production_cost_nonnegative'
        );

        Schema::dropIfExists('products');
    }
};
