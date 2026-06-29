<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('food_name');
            $table->enum('type', ['liked', 'disliked']);
            $table->timestamps();
            $table->unique(['food_name', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_preferences');
    }
};
