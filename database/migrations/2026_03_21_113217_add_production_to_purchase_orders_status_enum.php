<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For MySQL, we need to modify the ENUM definition
        DB::statement("ALTER TABLE scm_purchase_orders MODIFY status ENUM('draft', 'sent', 'production', 'shipping', 'delivered', 'completed') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        // Revert to the previous definition (adjust as needed)
        DB::statement("ALTER TABLE scm_purchase_orders MODIFY status ENUM('draft', 'sent', 'shipping', 'delivered', 'completed') NOT NULL DEFAULT 'draft'");
    }
};
