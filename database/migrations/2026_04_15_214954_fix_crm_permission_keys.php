<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::table('page_permissions')
            ->where('module', 'CRM')
            ->where('page', 'customers')
            ->update(['page' => 'customer_profiles']);
    }

    public function down()
    {
        DB::table('page_permissions')
            ->where('module', 'CRM')
            ->where('page', 'customer_profiles')
            ->update(['page' => 'customers']);
    }
};