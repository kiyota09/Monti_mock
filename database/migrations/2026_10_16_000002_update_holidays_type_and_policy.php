<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New holiday type option: Non-Working Day.
        DB::statement("ALTER TABLE holidays MODIFY holiday_type ENUM('regular', 'special_non_working', 'special_working', 'non_working') NOT NULL DEFAULT 'regular'");

        if (! Schema::hasColumn('holidays', 'work_policy')) {
            DB::statement("ALTER TABLE holidays ADD COLUMN work_policy ENUM('non_working', 'required_to_work') NOT NULL DEFAULT 'non_working' AFTER holiday_type");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('holidays', 'work_policy')) {
            DB::statement('ALTER TABLE holidays DROP COLUMN work_policy');
        }
        DB::statement("ALTER TABLE holidays MODIFY holiday_type ENUM('regular', 'special_non_working', 'special_working') NOT NULL DEFAULT 'regular'");
    }
};
