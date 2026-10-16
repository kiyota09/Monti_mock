<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('page_permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('page_permissions', 'permission_level')) {
                $table->enum('permission_level', ['view', 'edit'])->after('page')->notNull()->default('edit');
            }
        });
    }

    public function down()
    {
        Schema::table('page_permissions', function (Blueprint $table) {
            $table->dropColumn('permission_level');
        });
    }
};
