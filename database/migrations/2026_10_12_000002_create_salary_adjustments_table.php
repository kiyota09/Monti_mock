<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('old_salary', 12, 2)->nullable();
            $table->decimal('new_salary', 12, 2);
            $table->string('adjustment_type', 60)->default('Merit Increase');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->date('effective_date');
            // Draft → Pending Approval → Approved → Scheduled → Applied
            // (Rejected / Cancelled are terminal)
            $table->string('status', 30)->default('Draft');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('document_path', 255)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_adjustments');
    }
};
