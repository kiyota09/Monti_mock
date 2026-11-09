<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite stores this as a string and does not support MySQL ENUM DDL.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE crm_leads MODIFY COLUMN status ENUM('Inquiry', 'Negotiation', 'Approval Sent', 'Closed-Won', 'Lost', 'Converted') NOT NULL DEFAULT 'Inquiry'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE crm_leads MODIFY COLUMN status ENUM('Inquiry', 'Negotiation', 'Approval Sent', 'Closed-Won', 'Lost') NOT NULL DEFAULT 'Inquiry'");
        }
    }
};
