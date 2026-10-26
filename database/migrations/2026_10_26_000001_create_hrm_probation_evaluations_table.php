<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrm_probation_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date')->nullable();
            $table->json('scores')->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->text('comments')->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->string('recommendation', 40)->nullable();
            $table->string('status', 24)->default('Draft');
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'period_end']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_probation_evaluations');
    }
};
