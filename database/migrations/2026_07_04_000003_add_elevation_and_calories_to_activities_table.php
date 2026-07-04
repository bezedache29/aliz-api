<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->float('total_elevation_gain')->nullable()->after('elapsed_time');
            $table->float('calories')->nullable()->after('total_elevation_gain');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['total_elevation_gain', 'calories']);
        });
    }
};
