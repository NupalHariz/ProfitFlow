<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug', 'uq_product_categories_slug');
            $table->index(['is_active', 'name'], 'idx_product_categories_active_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
