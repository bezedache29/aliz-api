<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->text('description')->nullable()->after('cooking_method');
            $table->decimal('kcal_estimated', 8, 2)->nullable()->after('description');
            $table->decimal('proteines_estimated', 8, 2)->nullable()->after('kcal_estimated');
            $table->decimal('glucides_estimated', 8, 2)->nullable()->after('proteines_estimated');
            $table->decimal('lipides_estimated', 8, 2)->nullable()->after('glucides_estimated');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'kcal_estimated',
                'proteines_estimated',
                'glucides_estimated',
                'lipides_estimated',
            ]);
        });
    }
};
