<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_foods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('barcode')->nullable()->unique();
            $table->decimal('per100g_kcal', 8, 2);
            $table->decimal('per100g_proteines', 8, 2);
            $table->decimal('per100g_glucides', 8, 2);
            $table->decimal('per100g_lipides', 8, 2);
            $table->decimal('per100g_fibres', 8, 2)->nullable();
            $table->decimal('per100g_sel', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_foods');
    }
};
