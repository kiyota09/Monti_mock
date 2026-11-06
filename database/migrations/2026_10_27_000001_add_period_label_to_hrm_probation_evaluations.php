<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_probation_evaluations', function (Blueprint $table) {
            $table->string('period_label', 80)->nullable()->after('period_end');
        });
    }

    public function down(): void
    {
        Schema::table('hrm_probation_evaluations', function (Blueprint $table) {
            $table->dropColumn('period_label');
        });
    }
};
