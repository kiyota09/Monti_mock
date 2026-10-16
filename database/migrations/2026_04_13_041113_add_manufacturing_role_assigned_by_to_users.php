<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('manufacturing_role_assigned_by')->nullable()->after('manufacturing_role');
            $table->foreign('manufacturing_role_assigned_by')->references('id')->on('users');
        });
    }
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['manufacturing_role_assigned_by']);
            $table->dropColumn('manufacturing_role_assigned_by');
        });
    }
};
