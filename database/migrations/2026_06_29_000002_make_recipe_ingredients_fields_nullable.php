<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->string('food_id')->nullable()->change();
            $table->string('food_source')->nullable()->change();
            $table->decimal('per100g_fibres', 8, 2)->nullable()->change();
            $table->decimal('per100g_sel', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->string('food_id')->nullable(false)->change();
            $table->string('food_source')->nullable(false)->change();
            $table->decimal('per100g_fibres', 8, 2)->nullable(false)->change();
            $table->decimal('per100g_sel', 8, 2)->nullable(false)->change();
        });
    }
};
