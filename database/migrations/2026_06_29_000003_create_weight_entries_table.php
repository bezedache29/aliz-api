<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->float('weight')->nullable();
            $table->float('bmi')->nullable();
            $table->float('bodyfat')->nullable();
            $table->float('water')->nullable();
            $table->float('muscle')->nullable();
            $table->float('bone')->nullable();
            $table->float('bmr')->nullable();
            $table->float('protein')->nullable();
            $table->float('body_age')->nullable();
            $table->float('heart_rate')->nullable();
            $table->timestamp('measured_at');
            $table->timestamps();

            $table->unique('measured_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_entries');
    }
};
