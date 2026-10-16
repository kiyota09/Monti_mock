<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This table is synchronized with the HRM Staff Payroll Terminal UI.
     */
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            // Employee Identity
            $table->string('employee_id');
            $table->string('employee_name')->nullable();
            $table->enum('role', ['Staff', 'Manager'])->default('Staff');

            // Basic Pay & Days Worked
            $table->decimal('base_salary', 15, 2);
            $table->integer('days_worked')->default(0);
            $table->decimal('daily_rate', 15, 2)->default(0);
            $table->decimal('total_days_amt', 15, 2)->default(0);

            // Night Differential (Mapped to 'Night' columns in UI)
            $table->decimal('night_hours', 8, 2)->default(0);
            $table->decimal('night_rate', 10, 2)->default(0);
            $table->decimal('night_amt', 15, 2)->default(0);

            // Overtime (Mapped to 'Overtime' columns in UI)
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('ot_rate', 15, 2)->default(0);
            $table->decimal('ot_amt', 15, 2)->default(0);

            // Sunday / Special Holiday (Mapped to 'Sun/sp' columns in UI)
            $table->decimal('sunday_restday_hours', 8, 2)->default(0);
            $table->decimal('sun_sp_rate', 15, 2)->default(0);
            $table->decimal('sun_sp_amt', 15, 2)->default(0);
            $table->decimal('holiday_amt', 15, 2)->default(0);

            // Lateness (Mapped to 'Late(min)' columns in UI)
            $table->integer('late_minutes')->default(0);
            $table->decimal('late_rate_min', 10, 2)->default(0);
            $table->decimal('late_total_deduction', 15, 2)->default(0);

            // Statutory Deductions
            $table->decimal('sss_deduction', 15, 2)->default(0);
            $table->decimal('philhealth_deduction', 15, 2)->default(0);
            $table->decimal('pagibig_deduction', 15, 2)->default(0);
            $table->decimal('tax_withheld', 15, 2)->default(0);

            // Loans (SSS and PF Loans from UI)
            $table->decimal('sss_loan', 15, 2)->default(0);
            $table->decimal('pf_loan', 15, 2)->default(0);

            // Financial Totals and Review Status
            $table->decimal('gross_pay', 15, 2);
            $table->decimal('net_pay', 15, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
