<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->string('category')->nullable()->after('food_name');
            $table->string('unit')->nullable()->after('quantity_g');
            $table->string('state')->nullable()->after('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropColumn(['category', 'unit', 'state']);
        });
    }
};
