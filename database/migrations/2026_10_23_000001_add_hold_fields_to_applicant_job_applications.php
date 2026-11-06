<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automatic hold for an applicant's other active job applications once
     * one of their applications reaches onboarding.
     *
     * `status` is deliberately left untouched so the application keeps its
     * real recruitment stage while it is parked. The trigger application is
     * tracked separately so releasing/closing holds is deterministic.
     */
    public function up(): void
    {
        Schema::table('applicant_job_applications', function (Blueprint $table) {
            $table->boolean('is_on_hold')->default(false)->after('status');
            $table->string('previous_status', 30)->nullable()->after('is_on_hold');
            $table->text('hold_reason')->nullable()->after('previous_status');
            $table->foreignId('held_by_application_id')->nullable()->after('hold_reason')
                ->constrained('applicant_job_applications')->nullOnDelete();
            $table->index(['applicant_id', 'is_on_hold']);
        });
    }

    public function down(): void
    {
        Schema::table('applicant_job_applications', function (Blueprint $table) {
            $table->dropForeign(['held_by_application_id']);
            $table->dropIndex(['applicant_id', 'is_on_hold']);
            $table->dropColumn([
                'is_on_hold',
                'previous_status',
                'hold_reason',
                'held_by_application_id',
            ]);
        });
    }
};
