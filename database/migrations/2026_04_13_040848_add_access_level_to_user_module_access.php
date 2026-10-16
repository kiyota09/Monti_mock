<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('user_module_access', function (Blueprint $table) {
            $table->enum('access_level', ['view', 'edit'])->default('edit')->after('module');
        });
    }
    public function down()
    {
        Schema::table('user_module_access', function (Blueprint $table) {
            $table->dropColumn('access_level');
        });
    }
};
