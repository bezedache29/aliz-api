<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $table->string('food_id');
            $table->string('food_name');
            $table->string('food_source');
            $table->string('food_brand')->nullable();
            $table->string('food_barcode')->nullable();
            $table->decimal('per100g_kcal', 8, 2);
            $table->decimal('per100g_proteines', 8, 2);
            $table->decimal('per100g_glucides', 8, 2);
            $table->decimal('per100g_lipides', 8, 2);
            $table->decimal('per100g_fibres', 8, 2);
            $table->decimal('per100g_sel', 8, 2);
            $table->decimal('quantity_g', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
