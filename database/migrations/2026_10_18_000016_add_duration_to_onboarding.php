<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_onboarding_templates', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_days')->nullable()->after('work_mode');
        });

        Schema::table('hrm_onboardings', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_days')->nullable()->after('expected_completion_date');
            $table->timestamp('duration_started_at')->nullable()->after('duration_days');
            $table->timestamp('duration_reminder_sent_at')->nullable()->after('duration_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('hrm_onboardings', function (Blueprint $table) {
            $table->dropColumn(['duration_days', 'duration_started_at', 'duration_reminder_sent_at']);
        });

        Schema::table('hrm_onboarding_templates', function (Blueprint $table) {
            $table->dropColumn('duration_days');
        });
    }
};
