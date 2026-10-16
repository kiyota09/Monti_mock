<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weighted training evaluation for the HR Training page:
     * Attendance 20% | Knowledge 25% | Practical Skills 30% |
     * Work Behavior 15% | Safety Compliance 10%.
     * Final grade is auto-calculated as the weighted sum (0-100).
     */
    public function up(): void
    {
        Schema::table('trainee_grades', function (Blueprint $table) {
            $table->decimal('attendance', 5, 2)->nullable()->after('productivity');
            $table->decimal('knowledge', 5, 2)->nullable()->after('attendance');
            $table->decimal('practical_skills', 5, 2)->nullable()->after('knowledge');
            $table->decimal('work_behavior', 5, 2)->nullable()->after('practical_skills');
            $table->decimal('safety_compliance', 5, 2)->nullable()->after('work_behavior');
            $table->decimal('final_grade', 5, 2)->nullable()->after('total_percentage');
            $table->string('evaluation_status', 20)->default('Pending')->after('final_grade');
            $table->text('remarks')->nullable()->after('evaluation_status');
            $table->timestamp('evaluated_at')->nullable()->after('remarks');
            $table->foreignId('evaluated_by')->nullable()->after('evaluated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trainee_grades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluated_by');
            $table->dropColumn([
                'attendance',
                'knowledge',
                'practical_skills',
                'work_behavior',
                'safety_compliance',
                'final_grade',
                'evaluation_status',
                'remarks',
                'evaluated_at',
            ]);
        });
    }
};
