<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrm_advance_payments', function (Blueprint $table) {
            $table->id();
            $table->string('advance_number', 32)->unique()->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_id', 80);
            $table->string('employee_name', 160);
            $table->string('department', 120)->nullable();
            $table->string('position', 120)->nullable();
            $table->decimal('advance_amount', 15, 2);
            $table->text('reason');
            $table->date('request_date');
            $table->date('planned_release_date');
            $table->string('payment_method', 30);
            $table->string('repayment_method', 20);
            $table->unsignedSmallInteger('installments')->nullable();
            $table->decimal('installment_amount', 15, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('payroll_deduction_authorized')->default(false);
            $table->string('authorization_document_path')->nullable();
            $table->string('authorization_document_name')->nullable();
            $table->string('status', 32)->default('Draft');
            $table->decimal('total_repaid', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_reference', 100)->nullable();
            $table->date('actual_release_date')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'request_date']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('hrm_advance_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('hrm_advance_payments')->cascadeOnDelete();
            $table->string('stage', 20);
            $table->string('decision', 20);
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['advance_id', 'stage']);
        });

        Schema::create('hrm_advance_payroll_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('hrm_advance_payments')->cascadeOnDelete();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->decimal('deduction_amount', 15, 2);
            $table->string('status', 16)->default('reserved');
            $table->timestamps();
            $table->unique(['advance_id', 'payroll_id']);
            $table->index(['payroll_id', 'status']);
        });

        Schema::create('hrm_advance_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('hrm_advance_payments')->cascadeOnDelete();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->string('payroll_period', 80);
            $table->decimal('amount', 15, 2);
            $table->timestamp('repaid_at');
            $table->timestamps();
            $table->unique(['advance_id', 'payroll_id']);
        });

        Schema::create('hrm_advance_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('hrm_advance_payments')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('remarks')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['advance_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_advance_audit_logs');
        Schema::dropIfExists('hrm_advance_repayments');
        Schema::dropIfExists('hrm_advance_payroll_deductions');
        Schema::dropIfExists('hrm_advance_approvals');
        Schema::dropIfExists('hrm_advance_payments');
    }
};
