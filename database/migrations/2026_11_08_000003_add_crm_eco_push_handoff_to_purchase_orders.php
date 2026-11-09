<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('crm_passed_to_eco_at')->nullable()->after('client_po_number');
        });

        DB::table('purchase_orders')->whereExists(function ($query) {
            $query->select(DB::raw(1))->from('sales_orders')
                ->whereColumn('sales_orders.purchase_order_id', 'purchase_orders.po_number')
                ->whereColumn('sales_orders.created_at', '>=', 'purchase_orders.created_at');
        })->update(['crm_passed_to_eco_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('crm_passed_to_eco_at');
        });
    }
};
