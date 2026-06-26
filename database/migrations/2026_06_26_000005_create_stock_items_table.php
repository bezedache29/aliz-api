<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('food_name');
            $table->string('food_id')->nullable();
            $table->string('food_source')->nullable();
            $table->string('food_brand')->nullable();
            $table->string('food_barcode')->nullable();
            $table->decimal('per100g_kcal', 8, 2)->nullable();
            $table->decimal('per100g_proteines', 8, 2)->nullable();
            $table->decimal('per100g_glucides', 8, 2)->nullable();
            $table->decimal('per100g_lipides', 8, 2)->nullable();
            $table->decimal('quantity_g', 8, 2);
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            $table->index(['expiry_date', 'food_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
