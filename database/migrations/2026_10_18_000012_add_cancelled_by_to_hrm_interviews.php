<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records who cancelled an interview ('applicant' | 'hrm') so the UI can show
 * "Cancelled by Applicant" while the stored status stays 'Cancelled' and all
 * existing filters/metrics keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_interviews', function (Blueprint $table) {
            $table->string('cancelled_by', 20)->nullable()->after('cancel_reason'); // applicant|hrm
        });
    }

    public function down(): void
    {
        Schema::table('hrm_interviews', function (Blueprint $table) {
            $table->dropColumn('cancelled_by');
        });
    }
};
