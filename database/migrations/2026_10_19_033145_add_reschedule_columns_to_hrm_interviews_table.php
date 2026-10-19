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
        // Idempotent: some columns may already exist (earlier partial run on
        // this database), so only create the ones that are actually missing.
        Schema::table('hrm_interviews', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_interviews', 'reschedule_status')) {
                $table->string('reschedule_status', 30)->nullable()->after('status');
            }
            if (! Schema::hasColumn('hrm_interviews', 'reschedule_date')) {
                $table->date('reschedule_date')->nullable()->after('reschedule_status');
            }
            if (! Schema::hasColumn('hrm_interviews', 'reschedule_start_time')) {
                $table->string('reschedule_start_time', 10)->nullable()->after('reschedule_date');
            }
            if (! Schema::hasColumn('hrm_interviews', 'reschedule_end_time')) {
                $table->string('reschedule_end_time', 10)->nullable()->after('reschedule_start_time');
            }
            if (! Schema::hasColumn('hrm_interviews', 'reschedule_reason')) {
                $table->text('reschedule_reason')->nullable()->after('reschedule_end_time');
            }
            if (! Schema::hasColumn('hrm_interviews', 'reschedule_requested_at')) {
                $table->timestamp('reschedule_requested_at')->nullable()->after('reschedule_reason');
            }
            if (! Schema::hasColumn('hrm_interviews', 'rescheduled_from_id')) {
                $table->foreignId('rescheduled_from_id')->nullable()->constrained('hrm_interviews')->nullOnDelete()->after('reschedule_requested_at');
            }
            if (! Schema::hasColumn('hrm_interviews', 'rescheduled_to_id')) {
                $table->foreignId('rescheduled_to_id')->nullable()->constrained('hrm_interviews')->nullOnDelete()->after('rescheduled_from_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hrm_interviews', function (Blueprint $table) {
            $table->dropForeign(['rescheduled_from_id']);
            $table->dropForeign(['rescheduled_to_id']);
            $table->dropColumn([
                'reschedule_status',
                'reschedule_date',
                'reschedule_start_time',
                'reschedule_end_time',
                'reschedule_reason',
                'reschedule_requested_at',
                'rescheduled_from_id',
                'rescheduled_to_id',
            ]);
        });
    }
};
