<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // HR review & decision on the final evaluation (HR Staff =
        // reviewer and decision-maker; manager = evaluator/recommender).
        if (Schema::hasTable('trainee_grades')) {
            Schema::table('trainee_grades', function (Blueprint $table) {
                if (! Schema::hasColumn('trainee_grades', 'hr_review_status')) {
                    // Pending | Approved | Extended | Rejected
                    $table->string('hr_review_status', 20)->nullable()->after('manager_recommendation');
                }
                if (! Schema::hasColumn('trainee_grades', 'hr_reviewer_id')) {
                    $table->foreignId('hr_reviewer_id')->nullable()->after('hr_review_status')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('trainee_grades', 'hr_review_notes')) {
                    $table->text('hr_review_notes')->nullable()->after('hr_reviewer_id');
                }
                if (! Schema::hasColumn('trainee_grades', 'hr_reviewed_at')) {
                    $table->timestamp('hr_reviewed_at')->nullable()->after('hr_review_notes');
                }
            });
        }

        // Employment status is separate from position/company level
        // (e.g. Position "IT Manager" while Employment Status "Trainee",
        // becoming "Regular" only after HR approves regularization).
        // Regularization date already lives on users.regularization_date.
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'employment_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('employment_status', 30)->nullable()->after('position');
            });
        }

        // Backfill: current trainees are Trainees.
        try {
            \App\Models\Core\User::where('position', 'trainee')
                ->whereNull('employment_status')
                ->update(['employment_status' => 'Trainee']);
        } catch (\Throwable $e) {
            // Model may be unavailable during fresh installs; label logic
            // falls back to position-based derivation.
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('trainee_grades')) {
            Schema::table('trainee_grades', function (Blueprint $table) {
                foreach (['hr_reviewed_at', 'hr_review_notes', 'hr_reviewer_id', 'hr_review_status'] as $col) {
                    if (Schema::hasColumn('trainee_grades', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'employment_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('employment_status');
            });
        }
    }
};
