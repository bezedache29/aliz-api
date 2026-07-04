<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('strava_id')->unique();
            $table->string('name');
            $table->string('type');
            $table->float('distance')->nullable();
            $table->unsignedInteger('moving_time')->nullable();
            $table->unsignedInteger('elapsed_time')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
