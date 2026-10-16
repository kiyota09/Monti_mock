<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add shift assignment to job postings (Morning / Afternoon / Night /
     * Rotating / Flexible Shift dropdown in the posting form).
     */
    public function up(): void
    {
        Schema::table('hrm_job_postings', function (Blueprint $table) {
            $table->string('shift', 30)->nullable()->default('Morning Shift')->after('work_schedule');
        });
    }

    public function down(): void
    {
        Schema::table('hrm_job_postings', function (Blueprint $table) {
            $table->dropColumn('shift');
        });
    }
};
