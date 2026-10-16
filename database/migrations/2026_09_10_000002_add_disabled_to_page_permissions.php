<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `page_permissions` MODIFY `permission_level` ENUM('view','edit','disabled') NOT NULL DEFAULT 'edit'");
        }
        // sqlite/pgsql: no enum enforcement — validation layer governs levels.
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // Drop disabled rows first so the reverse ALTER succeeds.
            DB::table('page_permissions')->where('permission_level', 'disabled')->delete();
            DB::statement("ALTER TABLE `page_permissions` MODIFY `permission_level` ENUM('view','edit') NOT NULL DEFAULT 'edit'");
        }
    }
};
