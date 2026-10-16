<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('manufacturing_role', [
                'knitting_yarn',
                'dyeing_color',
                'dyeing_fabric_softener',
                'dyeing_squeezer',
                'dyeing_ironing',
                'dyeing_forming',
                'dyeing_packaging',
                'maintenance_checker',
                'checker_quality',
            ])->nullable()->after('position');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('manufacturing_role');
        });
    }
};
