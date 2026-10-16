<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename general_manager → special_officer across position ENUMs.
 * Historical migration files are left untouched; this migration
 * carries existing rows forward so no account is stranded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // 1. Widen the ENUM so both values coexist under strict mode.
        DB::statement("ALTER TABLE users MODIFY COLUMN position ENUM('manager', 'staff', 'trainee', 'secretary', 'general_manager', 'special_officer', 'vice_president') NOT NULL");

        // 2. Carry existing rows forward.
        DB::table('users')->where('position', 'general_manager')->update(['position' => 'special_officer']);

        // 3. Drop the old value.
        DB::statement("ALTER TABLE users MODIFY COLUMN position ENUM('manager', 'staff', 'trainee', 'secretary', 'special_officer', 'vice_president') NOT NULL");

        if (Schema::hasColumn('payroll_sets', 'position')) {
            DB::statement("ALTER TABLE payroll_sets MODIFY COLUMN position ENUM('staff', 'manager', 'general_manager', 'special_officer', 'secretary') NOT NULL DEFAULT 'staff'");
            DB::table('payroll_sets')->where('position', 'general_manager')->update(['position' => 'special_officer']);
            DB::statement("ALTER TABLE payroll_sets MODIFY COLUMN position ENUM('staff', 'manager', 'special_officer', 'secretary') NOT NULL DEFAULT 'staff'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN position ENUM('manager', 'staff', 'trainee', 'secretary', 'general_manager', 'special_officer', 'vice_president') NOT NULL");
        DB::table('users')->where('position', 'special_officer')->update(['position' => 'general_manager']);
        DB::statement("ALTER TABLE users MODIFY COLUMN position ENUM('manager', 'staff', 'trainee', 'secretary', 'general_manager', 'vice_president') NOT NULL");

        if (Schema::hasColumn('payroll_sets', 'position')) {
            DB::statement("ALTER TABLE payroll_sets MODIFY COLUMN position ENUM('staff', 'manager', 'general_manager', 'special_officer', 'secretary') NOT NULL DEFAULT 'staff'");
            DB::table('payroll_sets')->where('position', 'special_officer')->update(['position' => 'general_manager']);
            DB::statement("ALTER TABLE payroll_sets MODIFY COLUMN position ENUM('staff', 'manager', 'general_manager', 'secretary') NOT NULL DEFAULT 'staff'");
        }
    }
};
