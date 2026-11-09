<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_orders')->where('status', 'pending')->whereNotNull('purchase_order_id')
            ->where(function ($query) {
                $query->whereNotExists(function ($parent) {
                    $parent->select(DB::raw(1))->from('purchase_orders')
                        ->whereColumn('purchase_orders.po_number', 'sales_orders.purchase_order_id');
                })->orWhereExists(function ($parent) {
                    $parent->select(DB::raw(1))->from('purchase_orders')
                        ->whereColumn('purchase_orders.po_number', 'sales_orders.purchase_order_id')
                        ->whereColumn('sales_orders.created_at', '<', 'purchase_orders.created_at');
                });
            })->update([
                'status' => 'cancelled',
                'cancel_reason' => 'Stale sales order linked to a deleted or recreated purchase order number.',
                'updated_at' => now(),
            ]);

        DB::table('purchase_orders')->whereNotNull('inquiry_id')
            ->whereIn('status', ['credit_review', 'crm_recipe_review'])
            ->whereNotNull('crm_passed_to_eco_at')
            ->whereNotExists(function ($salesOrder) {
                $salesOrder->select(DB::raw(1))->from('sales_orders')
                    ->whereColumn('sales_orders.purchase_order_id', 'purchase_orders.po_number')
                    ->whereColumn('sales_orders.created_at', '>=', 'purchase_orders.created_at')
                    ->where('sales_orders.status', '!=', 'cancelled');
            })->update([
                'status' => 'crm_recipe_review',
                'crm_passed_to_eco_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Stale sales orders are intentionally retained as cancelled audit records.
    }
};
