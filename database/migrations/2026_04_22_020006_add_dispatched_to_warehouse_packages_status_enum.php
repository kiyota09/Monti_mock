<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE warehouse_packages MODIFY status ENUM('pending', 'pushed_to_logistics', 'dispatched', 'delivered') NOT NULL DEFAULT 'pending'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE warehouse_packages MODIFY status ENUM('pending', 'pushed_to_logistics', 'delivered') NOT NULL DEFAULT 'pending'");
    }
};