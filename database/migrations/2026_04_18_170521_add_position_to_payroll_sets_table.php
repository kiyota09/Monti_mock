<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPositionToPayrollSetsTable extends Migration
{
    public function up()
    {
        Schema::table('payroll_sets', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_sets', 'position')) {
                $table->enum('position', ['staff', 'manager', 'general_manager', 'secretary'])
                      ->after('id')
                      ->notNull()
                      ->default('staff');
            }
        });
    }

    public function down()
    {
        Schema::table('payroll_sets', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_sets', 'position')) {
                $table->dropColumn('position');
            }
        });
    }
}