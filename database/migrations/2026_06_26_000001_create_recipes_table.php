<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('category');
            $table->string('meal')->nullable();
            $table->json('steps');
            $table->boolean('is_favorite')->default(false);
            $table->unsignedSmallInteger('prep_time')->nullable();
            $table->unsignedSmallInteger('cook_time')->nullable();
            $table->json('seasons');
            $table->string('cooking_method')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
