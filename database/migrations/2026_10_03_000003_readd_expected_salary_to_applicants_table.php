<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Re-add expected_salary dropped by
     * 2026_04_08_020930_drop_expected_saalary_andexperience.
     * Code (ApplicationController@store, ApplicantProfileService,
     * Onboarding StatusController, EmployeeDirectory) still reads/writes
     * this column, so inserts fail with 1054 without it.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('applicants', 'expected_salary')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->decimal('expected_salary', 12, 2)->nullable()->after('position_applied');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('applicants', 'expected_salary')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->dropColumn('expected_salary');
            });
        }
    }
};
