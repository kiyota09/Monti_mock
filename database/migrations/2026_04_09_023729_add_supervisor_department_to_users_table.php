<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('supervisor_department', ['knitting', 'dyeing', 'maintenance'])
                  ->nullable()
                  ->after('is_manufacturing_supervisor');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('supervisor_department');
        });
    }
};