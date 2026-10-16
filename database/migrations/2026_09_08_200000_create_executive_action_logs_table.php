<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Executive decision log: every approve/reject taken in the CEO
 * Approvals Center. audit_logs requires a user target; executive
 * decisions target records (payroll, vendors, orders), so they get
 * their own tamper-proof table (append-only by convention —
 * no update/delete endpoints exist).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executive_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users');
            // payroll|vendor|credit
            $table->string('action_type', 32);
            $table->string('subject_label');
            $table->unsignedBigInteger('subject_id')->nullable();
            // approved|rejected
            $table->string('decision', 16);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_action_logs');
    }
};
