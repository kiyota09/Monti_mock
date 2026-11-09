<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New holiday type option: Non-Working Day.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE holidays MODIFY holiday_type ENUM('regular', 'special_non_working', 'special_working', 'non_working') NOT NULL DEFAULT 'regular'");
            if (! Schema::hasColumn('holidays', 'work_policy')) {
                DB::statement("ALTER TABLE holidays ADD COLUMN work_policy ENUM('non_working', 'required_to_work') NOT NULL DEFAULT 'non_working' AFTER holiday_type");
            }
        } else {
            Schema::table('holidays', function (Blueprint $table) {
                $table->enum('holiday_type', ['regular', 'special_non_working', 'special_working', 'non_working'])->default('regular')->change();
                if (! Schema::hasColumn('holidays', 'work_policy')) {
                    $table->enum('work_policy', ['non_working', 'required_to_work'])->default('non_working');
                }
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            if (Schema::hasColumn('holidays', 'work_policy')) {
                DB::statement('ALTER TABLE holidays DROP COLUMN work_policy');
            }
            DB::statement("ALTER TABLE holidays MODIFY holiday_type ENUM('regular', 'special_non_working', 'special_working') NOT NULL DEFAULT 'regular'");
        } else {
            Schema::table('holidays', function (Blueprint $table) {
                if (Schema::hasColumn('holidays', 'work_policy')) {
                    $table->dropColumn('work_policy');
                }
                $table->enum('holiday_type', ['regular', 'special_non_working', 'special_working'])->default('regular')->change();
            });
        }
    }
};
