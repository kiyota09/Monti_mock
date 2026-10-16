<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn('supervisor_id');
            $table->dropForeign(['manager_id']);
            $table->dropColumn('manager_id');
        });
    }
};