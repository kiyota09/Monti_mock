<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            // Alter enum to include 'Archived' (using DB::statement for MySQL)
            DB::statement("ALTER TABLE crm_leads MODIFY status ENUM('Inquiry','Negotiation','Approval Sent','Closed-Won','Lost','Converted','Archived') NOT NULL DEFAULT 'Inquiry'");
        });
    }

    public function down()
    {
        DB::statement("ALTER TABLE crm_leads MODIFY status ENUM('Inquiry','Negotiation','Approval Sent','Closed-Won','Lost','Converted') NOT NULL DEFAULT 'Inquiry'");
    }
};
