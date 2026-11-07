<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('man_staff_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_department', 32);
            $table->string('target_department', 32);
            $table->string('target_role', 64);
            $table->text('reason')->nullable();
            $table->string('status', 24)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['source_department', 'status'], 'man_transfer_source_status_index');
            $table->index(['requester_supervisor_id', 'status'], 'man_transfer_requester_status_index');
            $table->index(['staff_user_id', 'status'], 'man_transfer_staff_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('man_staff_transfer_requests');
    }
};
