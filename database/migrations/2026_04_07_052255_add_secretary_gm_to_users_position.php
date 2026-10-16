<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN position ENUM('manager', 'staff', 'trainee', 'secretary', 'general_manager') NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN position ENUM('manager', 'staff', 'trainee') NOT NULL");
    }
};