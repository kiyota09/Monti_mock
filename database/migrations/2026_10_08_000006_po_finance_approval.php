<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Finance approval gate on supplier purchase orders: an accepted
     * quotation creates the PO as finance-pending; FIN approves (Send PO
     * unlocks in PRO receipts) or declines (PRO can return the material
     * request to the queue). Pre-existing POs predate the gate and are
     * grandfathered as approved.
     */
    public function up(): void
    {
        Schema::table('scm_purchase_orders', function (Blueprint $table) {
            $table->string('finance_status', 16)->default('pending')->after('status');
            $table->unsignedBigInteger('finance_decided_by')->nullable()->after('finance_status');
            $table->timestamp('finance_decided_at')->nullable()->after('finance_decided_by');
            $table->text('finance_remarks')->nullable()->after('finance_decided_at');
        });

        DB::table('scm_purchase_orders')->update(['finance_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('scm_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['finance_status', 'finance_decided_by', 'finance_decided_at', 'finance_remarks']);
        });
    }
};
