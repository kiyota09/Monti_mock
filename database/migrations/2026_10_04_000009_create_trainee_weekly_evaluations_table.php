<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainee_weekly_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('week_number')->default(1);
            $table->decimal('attendance', 4, 2)->nullable();
            $table->decimal('knowledge', 4, 2)->nullable();
            $table->decimal('practical_skills', 4, 2)->nullable();
            $table->decimal('work_behavior', 4, 2)->nullable();
            $table->decimal('safety_compliance', 4, 2)->nullable();
            $table->decimal('overall', 4, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('recommendation', 40)->nullable(); // continue | ready_final | early_termination
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'week_number']);
        });

        if (Schema::hasTable('trainee_grades')) {
            Schema::table('trainee_grades', function (Blueprint $table) {
                if (! Schema::hasColumn('trainee_grades', 'manager_recommendation')) {
                    $table->string('manager_recommendation', 40)->nullable()->after('evaluation_status');
                }
                if (! Schema::hasColumn('trainee_grades', 'final_notes')) {
                    $table->text('final_notes')->nullable()->after('manager_recommendation');
                }
                if (! Schema::hasColumn('trainee_grades', 'weekly_recommendation')) {
                    $table->string('weekly_recommendation', 40)->nullable()->after('final_notes');
                }
                if (! Schema::hasColumn('trainee_grades', 'weekly_notes')) {
                    $table->text('weekly_notes')->nullable()->after('weekly_recommendation');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trainee_weekly_evaluations');

        if (Schema::hasTable('trainee_grades')) {
            Schema::table('trainee_grades', function (Blueprint $table) {
                foreach (['weekly_notes', 'weekly_recommendation', 'final_notes', 'manager_recommendation'] as $col) {
                    if (Schema::hasColumn('trainee_grades', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
