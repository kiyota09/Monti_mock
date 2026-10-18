<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'Converted' to the status ENUM
        DB::statement("ALTER TABLE crm_leads MODIFY COLUMN status ENUM('Inquiry', 'Negotiation', 'Approval Sent', 'Closed-Won', 'Lost', 'Converted') NOT NULL DEFAULT 'Inquiry'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'Converted' from the ENUM (revert to original)
        DB::statement("ALTER TABLE crm_leads MODIFY COLUMN status ENUM('Inquiry', 'Negotiation', 'Approval Sent', 'Closed-Won', 'Lost') NOT NULL DEFAULT 'Inquiry'");
    }
};
