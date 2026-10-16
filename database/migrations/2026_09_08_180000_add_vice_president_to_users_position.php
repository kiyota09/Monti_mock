<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vice President seat: second in command, modelled as role=CEO +
 * position=vice_president so every CEO bypass applies by construction.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN position ENUM('manager', 'staff', 'trainee', 'secretary', 'general_manager', 'vice_president') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN position ENUM('manager', 'staff', 'trainee', 'secretary', 'general_manager') NOT NULL");
    }
};
