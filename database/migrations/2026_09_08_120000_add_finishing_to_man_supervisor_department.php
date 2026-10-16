<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restructure: manufacturing is handled by 4 department supervisors
 * (knitting, dyeing, finishing, maintenance) — no manufacturing manager.
 *
 * Adds 'finishing' (Checker Quality staff) to users.supervisor_department.
 * MySQL-only ALTER (SQLite has no ENUM enforcement; other drivers skip).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE `users` MODIFY COLUMN `supervisor_department` " .
            "ENUM('knitting','dyeing','finishing','maintenance') NULL"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // NOTE: downgrading while 'finishing' rows exist will fail on MySQL.
        // Reassign finishing supervisors to another department first.
        DB::statement(
            "ALTER TABLE `users` MODIFY COLUMN `supervisor_department` " .
            "ENUM('knitting','dyeing','maintenance') NULL"
        );
    }
};
