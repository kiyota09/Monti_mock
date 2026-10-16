<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('page_permissions', function (Blueprint $table) {
            // Use 'permission_level' to match the controller
            $table->enum('permission_level', ['view', 'edit'])->default('edit')->after('page');
        });
    }
    public function down()
    {
        Schema::table('page_permissions', function (Blueprint $table) {
            $table->dropColumn('permission_level');
        });
    }
};