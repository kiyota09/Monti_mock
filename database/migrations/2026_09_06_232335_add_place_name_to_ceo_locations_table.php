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
        Schema::table('ceo_locations', function (Blueprint $table) {
            $table->string('place_name', 500)->nullable()->after('label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ceo_locations', function (Blueprint $table) {
            $table->dropColumn('place_name');
        });
    }
};
