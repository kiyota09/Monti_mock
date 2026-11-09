<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Include trainees in payroll: rate set per trainee type + Trainee
     * role value on payroll rows. Seeds a default trainee stipend set.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payroll_sets MODIFY COLUMN employee_type ENUM('staff','manager','trainee') NOT NULL DEFAULT 'staff'");
            DB::statement("ALTER TABLE payroll_sets MODIFY COLUMN position ENUM('staff','manager','special_officer','secretary','trainee') NOT NULL DEFAULT 'staff'");
            DB::statement("ALTER TABLE payrolls MODIFY COLUMN role ENUM('Staff','Manager','Trainee') NOT NULL DEFAULT 'Staff'");
        } else {
            Schema::table('payroll_sets', function (Blueprint $table) {
                $table->enum('employee_type', ['staff', 'manager', 'trainee'])->default('staff')->change();
            });
        }

        if (! DB::table('payroll_sets')->where('position', 'trainee')->exists()) {
            DB::table('payroll_sets')->insert([
                'name' => 'Default Trainee Stipend',
                'position' => 'trainee',
                'employee_type' => 'trainee',
                'base_salary' => 15000,
                'ot_rate' => 1.25,
                'night_rate' => 0.10,
                'late_rate_per_minute' => 5.00,
                'sunday_rate' => 1.30,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('payroll_sets')->where('position', 'trainee')->delete();
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payrolls MODIFY COLUMN role ENUM('Staff','Manager') NOT NULL DEFAULT 'Staff'");
            DB::statement("ALTER TABLE payroll_sets MODIFY COLUMN position ENUM('staff','manager','special_officer','secretary') NOT NULL DEFAULT 'staff'");
            DB::statement("ALTER TABLE payroll_sets MODIFY COLUMN employee_type ENUM('staff','manager') NOT NULL DEFAULT 'staff'");
        }
    }
};
