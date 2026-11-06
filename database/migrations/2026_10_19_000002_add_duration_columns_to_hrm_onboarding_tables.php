<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('hrm_onboarding_templates', 'duration_days')) {
            Schema::table('hrm_onboarding_templates', function (Blueprint $table) {
                $table->unsignedInteger('duration_days')->nullable()->after('work_mode');
            });
        }

        foreach (['duration_days', 'duration_started_at', 'duration_reminder_sent_at'] as $column) {
            if (!Schema::hasColumn('hrm_onboardings', $column)) {
                Schema::table('hrm_onboardings', function (Blueprint $table) use ($column) {
                    match ($column) {
                        'duration_days' => $table->unsignedInteger($column)->nullable()->after('expected_completion_date'),
                        'duration_started_at', 'duration_reminder_sent_at' => $table->timestamp($column)->nullable(),
                    };
                });
            }
        }
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
