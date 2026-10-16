<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Split the executive roles: the President keeps role CEO while the
     * Vice President moves to role COO (position stays vice_president).
     *
     * Route/model guards treat CEO as the all-access overseer; VP access
     * is granted explicitly via role:COO gates, so the President keeps
     * oversight (CEO bypass) without sharing one role value.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('HRM','SCM','FIN','MAN','INV','ORD','WAR','CRM','ECO','PRO','PROJ','IT','LOG','CEO','COO') NOT NULL DEFAULT 'HRM'");

        DB::table('users')
            ->where('role', 'CEO')
            ->where('position', 'vice_president')
            ->update(['role' => 'COO', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'COO')
            ->update(['role' => 'CEO', 'updated_at' => now()]);

        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('HRM','SCM','FIN','MAN','INV','ORD','WAR','CRM','ECO','PRO','PROJ','IT','LOG','CEO') NOT NULL DEFAULT 'HRM'");
    }
};
