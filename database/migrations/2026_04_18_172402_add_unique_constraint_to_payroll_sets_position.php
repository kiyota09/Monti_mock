<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueConstraintToPayrollSetsPosition extends Migration
{
    public function up()
    {
        try {
            Schema::table('payroll_sets', function (Blueprint $table) {
                $table->unique('position');
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Ignore duplicate key name error (1061)
            if (!str_contains($e->getMessage(), 'Duplicate key name')) {
                throw $e;
            }
        }
    }

    public function down()
    {
        Schema::table('payroll_sets', function (Blueprint $table) {
            $table->dropUnique('payroll_sets_position_unique');
        });
    }
}