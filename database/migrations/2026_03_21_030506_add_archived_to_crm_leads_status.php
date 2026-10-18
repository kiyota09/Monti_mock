
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE crm_leads
                MODIFY COLUMN status
                ENUM(
                    'Inquiry',
                    'Negotiation',
                    'Approval Sent',
                    'Closed-Won',
                    'Lost',
                    'Converted',
                    'Archived'
                ) NOT NULL DEFAULT 'Inquiry'
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE crm_leads
                MODIFY COLUMN status
                ENUM(
                    'Inquiry',
                    'Negotiation',
                    'Approval Sent',
                    'Closed-Won',
                    'Lost',
                    'Converted'
                ) NOT NULL DEFAULT 'Inquiry'
            ");
        }
    }
};
