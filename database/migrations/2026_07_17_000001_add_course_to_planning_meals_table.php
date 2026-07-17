<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_meals', function (Blueprint $table) {
            $table->string('course')->default('')->after('meal_type');
        });

        Schema::table('planning_meals', function (Blueprint $table) {
            $table->dropUnique(['date', 'meal_type']);
            $table->unique(['date', 'meal_type', 'course']);
        });
    }

    public function down(): void
    {
        Schema::table('planning_meals', function (Blueprint $table) {
            $table->dropUnique(['date', 'meal_type', 'course']);
            $table->dropColumn('course');
            $table->unique(['date', 'meal_type']);
        });
    }
};
