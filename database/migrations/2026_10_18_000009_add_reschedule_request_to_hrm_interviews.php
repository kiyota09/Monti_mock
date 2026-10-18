<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applicant-initiated reschedule requests: the applicant proposes a new
 * date/time that stays PENDING until the interviewer approves or denies it.
 * The live date/start_time/end_time columns are only touched on approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_interviews', function (Blueprint $table) {
            $table->string('reschedule_status', 20)->nullable()->after('cancel_reason'); // requested|approved|denied
            $table->date('reschedule_date')->nullable()->after('reschedule_status');
            $table->string('reschedule_start_time', 10)->nullable()->after('reschedule_date');
            $table->string('reschedule_end_time', 10)->nullable()->after('reschedule_start_time');
            $table->text('reschedule_reason')->nullable()->after('reschedule_end_time');
            $table->timestamp('reschedule_requested_at')->nullable()->after('reschedule_reason');
        });
    }

    public function down(): void
    {
        Schema::table('hrm_interviews', function (Blueprint $table) {
            $table->dropColumn([
                'reschedule_status',
                'reschedule_date',
                'reschedule_start_time',
                'reschedule_end_time',
                'reschedule_reason',
                'reschedule_requested_at',
            ]);
        });
    }
};
