<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'HRM','SCM','FIN','MAN','INV','ORD','WAR','CRM','ECO','PRO','PROJ','IT','CEO','LOG'
        ) NOT NULL DEFAULT 'HRM'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'HRM','SCM','FIN','MAN','INV','ORD','WAR','CRM','ECO','PRO','PROJ','IT','CEO'
        ) NOT NULL DEFAULT 'HRM'");
    }
};