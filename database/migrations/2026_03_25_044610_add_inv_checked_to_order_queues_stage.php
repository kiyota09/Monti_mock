<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE order_queues MODIFY stage ENUM('eco_approved','scm_received','inv_check','inv_checked','man_production','completed') NOT NULL DEFAULT 'eco_approved'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE order_queues MODIFY stage ENUM('eco_approved','scm_received','inv_check','man_production','completed') NOT NULL DEFAULT 'eco_approved'");
    }
};
