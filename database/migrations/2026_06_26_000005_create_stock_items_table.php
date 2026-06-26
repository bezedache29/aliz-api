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
            $table->float('per100g_kcal')->nullable();
            $table->float('per100g_proteines')->nullable();
            $table->float('per100g_glucides')->nullable();
            $table->float('per100g_lipides')->nullable();
            $table->float('quantity_g');
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
