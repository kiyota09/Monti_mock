<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('probation_duration_months')->nullable()->after('probation_end_date');
            $table->unsignedTinyInteger('probation_evaluation_interval_months')->nullable()->after('probation_duration_months');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['probation_duration_months', 'probation_evaluation_interval_months']);
        });
    }
};
