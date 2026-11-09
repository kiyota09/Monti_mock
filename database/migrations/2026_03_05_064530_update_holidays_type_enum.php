<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE holidays MODIFY holiday_type ENUM('regular', 'special_non_working', 'special_working') NOT NULL DEFAULT 'regular'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE holidays MODIFY holiday_type ENUM('Normal', 'Special') NOT NULL DEFAULT 'Normal'");
        }
    }
};
