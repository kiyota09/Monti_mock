<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Table for actual Daily Attendance Logs (Clock-in/Clock-out records)
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date'); // The day the log belongs to

            /**
             * CHANGED: Using string instead of time to support "08:00 AM" format.
             * Standard time columns in MySQL do not support AM/PM indicators.
             */
            $table->string('clock_in')->nullable();
            $table->string('clock_out')->nullable();

            $table->string('status')->default('Absent'); // e.g., On-Time, Late, Absent
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        // 2. Table for Shift Management (The Scheduler/Calendar Data)
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('dept_code')->nullable(); // HRM, SCM, FIN, MAN, INV, ORD, WAR, CRM, ECO
            $table->enum('shift_type', ['Morning', 'Afternoon', 'Graveyard']); // Matches the 3-column modal
            $table->date('effective_date'); // The specific date clicked on the calendar
            $table->string('schedule_range')->nullable()->default('8AM - 5PM'); // Display string for UI
            $table->timestamps();

            // Ensures an employee doesn't have duplicate/conflicting shifts for a single day.
            $table->unique(['user_id', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_shifts');
        Schema::dropIfExists('attendance_logs');
    }
};
