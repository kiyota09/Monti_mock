<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adopt the semi-monthly Draft -> Submitted -> Approved -> Paid workflow.
     * Keeps legacy rows readable: pending -> Submitted, approved stays Approved,
     * rejected -> Draft. Adds spec inputs (allowance, absences, period, etc.)
     * so statuses and attendance inputs are stored PER PAY PERIOD.
     */
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'period')) {
                $table->string('period', 60)->nullable()->after('role');
            }
            if (!Schema::hasColumn('payrolls', 'monthly_salary')) {
                $table->decimal('monthly_salary', 15, 2)->default(0)->after('base_salary');
            }
            if (!Schema::hasColumn('payrolls', 'allowance')) {
                $table->decimal('allowance', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('payrolls', 'absent_days')) {
                $table->decimal('absent_days', 8, 2)->default(0);
            }
            if (!Schema::hasColumn('payrolls', 'holiday_days')) {
                $table->decimal('holiday_days', 8, 2)->default(0);
            }
            if (!Schema::hasColumn('payrolls', 'department')) {
                $table->string('department', 80)->nullable();
            }
            if (!Schema::hasColumn('payrolls', 'job_title')) {
                $table->string('job_title', 120)->nullable();
            }
            if (!Schema::hasColumn('payrolls', 'employer_sss')) {
                $table->decimal('employer_sss', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('payrolls', 'employer_philhealth')) {
                $table->decimal('employer_philhealth', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('payrolls', 'employer_pagibig')) {
                $table->decimal('employer_pagibig', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('payrolls', 'accrual_13th')) {
                $table->decimal('accrual_13th', 15, 2)->default(0);
            }
        });

        // Status as VARCHAR (ENUM would collide case-insensitively on
        // 'Approved' vs legacy 'approved'). Workflow enforced in code:
        // Draft -> Submitted -> Approved -> Paid (Paid is final).
        try {
            DB::statement("ALTER TABLE payrolls MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Draft'");
        } catch (\Throwable $e) {
            // Non-MySQL driver: skip.
        }

        // Map legacy statuses to the new workflow.
        try {
            DB::table('payrolls')->where('status', 'pending')->update(['status' => 'Submitted']);
            DB::table('payrolls')->where('status', 'approved')->update(['status' => 'Approved']);
            DB::table('payrolls')->where('status', 'rejected')->update(['status' => 'Draft']);
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $cols = ['period','monthly_salary','allowance','absent_days','holiday_days','department','job_title','employer_sss','employer_philhealth','employer_pagibig','accrual_13th'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('payrolls', $c)) $table->dropColumn($c);
            }
        });
    }
};
