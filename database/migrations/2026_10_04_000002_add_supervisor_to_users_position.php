<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allow supervisor in users.position so the HRM Onboarding
 * CREATE EMPLOYEE System Position dropdown can persist it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN position ENUM('manager', 'staff', 'trainee', 'supervisor', 'secretary', 'special_officer', 'vice_president') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('users')->where('position', 'supervisor')->update(['position' => 'staff']);
        DB::statement("ALTER TABLE users MODIFY COLUMN position ENUM('manager', 'staff', 'trainee', 'secretary', 'special_officer', 'vice_president') NOT NULL");
    }
};
