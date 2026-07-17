<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->string('meal_type');
            $table->string('course')->nullable();
            $table->string('name');
            $table->decimal('kcal', 8, 2);
            $table->decimal('proteines', 8, 2);
            $table->decimal('glucides', 8, 2);
            $table->decimal('lipides', 8, 2);
            $table->decimal('quantity_g', 8, 2)->nullable();
            $table->decimal('per100g_kcal', 8, 2)->nullable();
            $table->decimal('per100g_proteines', 8, 2)->nullable();
            $table->decimal('per100g_glucides', 8, 2)->nullable();
            $table->decimal('per100g_lipides', 8, 2)->nullable();
            $table->json('stock_deductions')->nullable();
            $table->string('source')->default('manual');
            $table->string('suggestion_status')->nullable();
            $table->timestamps();
            $table->index(['date', 'meal_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
