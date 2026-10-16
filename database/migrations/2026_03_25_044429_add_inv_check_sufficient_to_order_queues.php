<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('order_queues', function (Blueprint $table) {
            $table->boolean('inv_check_sufficient')->nullable()->after('inv_checked_at');
        });
    }

    public function down()
    {
        Schema::table('order_queues', function (Blueprint $table) {
            $table->dropColumn('inv_check_sufficient');
        });
    }
};
