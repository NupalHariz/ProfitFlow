<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('code', 80);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id');
            $table->timestamps();

            $table->unique('code', 'uq_profit_schemes_code');
            $table->index(['is_active', 'name'], 'idx_profit_schemes_active_name');
            $table->foreign('created_by_user_id', 'fk_profit_schemes_creator')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_schemes');
    }
};
