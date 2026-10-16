<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retraining cycle details captured from the Schedule Retraining form:
     * start date (required), end date (optional), reason (required),
     * notes (optional). Holds the latest cycle; the count keeps history.
     */
    public function up(): void
    {
        Schema::table('trainee_grades', function (Blueprint $table) {
            $table->date('retraining_start_date')->nullable()->after('retraining_count');
            $table->date('retraining_end_date')->nullable()->after('retraining_start_date');
            $table->text('retraining_reason')->nullable()->after('retraining_end_date');
            $table->text('retraining_notes')->nullable()->after('retraining_reason');
        });
    }

    public function down(): void
    {
        Schema::table('trainee_grades', function (Blueprint $table) {
            $table->dropColumn([
                'retraining_start_date',
                'retraining_end_date',
                'retraining_reason',
                'retraining_notes',
            ]);
        });
    }
};
