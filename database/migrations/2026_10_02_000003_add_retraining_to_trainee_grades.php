<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retraining cycle for failed evaluations:
     * retraining = this trainee has been scheduled for retraining,
     * retraining_count = how many retraining cycles have been scheduled.
     */
    public function up(): void
    {
        Schema::table('trainee_grades', function (Blueprint $table) {
            $table->boolean('retraining')->default(false)->after('evaluated_by');
            $table->unsignedInteger('retraining_count')->default(0)->after('retraining');
        });
    }

    public function down(): void
    {
        Schema::table('trainee_grades', function (Blueprint $table) {
            $table->dropColumn(['retraining', 'retraining_count']);
        });
    }
};
