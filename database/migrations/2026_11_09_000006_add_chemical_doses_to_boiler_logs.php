<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boiler_logs', function (Blueprint $table) {
            $table->json('chemical_doses')->nullable()->after('chemical_dosing');
        });
    }

    public function down(): void
    {
        Schema::table('boiler_logs', function (Blueprint $table) {
            $table->dropColumn('chemical_doses');
        });
    }
};
