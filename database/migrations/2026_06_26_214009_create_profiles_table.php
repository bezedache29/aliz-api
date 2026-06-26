<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->unsignedTinyInteger('age');
            $table->enum('gender', ['male', 'female']);
            $table->unsignedSmallInteger('height_cm');
            $table->decimal('current_weight_kg', 5, 2);
            $table->decimal('target_weight_kg', 5, 2);
            $table->enum('activity_level', ['sedentary', 'light', 'moderate', 'active', 'very_active']);
            $table->decimal('weight_loss_rate_kg', 3, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
