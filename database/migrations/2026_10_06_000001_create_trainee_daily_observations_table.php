<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainee_daily_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->decimal('knowledge', 4, 2)->nullable();
            $table->decimal('practical_skills', 4, 2)->nullable();
            $table->decimal('work_behavior', 4, 2)->nullable();
            $table->decimal('safety_compliance', 4, 2)->nullable();
            $table->text('knowledge_notes')->nullable();
            $table->text('practical_skills_notes')->nullable();
            $table->text('work_behavior_notes')->nullable();
            $table->text('safety_compliance_notes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainee_daily_observations');
    }
};
