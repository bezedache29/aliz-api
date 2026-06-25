<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_meals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->string('meal_type');
            $table->foreignUuid('recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->unique(['date', 'meal_type']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_meals');
    }
};
