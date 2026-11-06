<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->timestamp('knitting_handoff_requested_at')->nullable()->after('knitting_done_by');
            $table->unsignedBigInteger('knitting_handoff_requested_by')->nullable()->after('knitting_handoff_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['knitting_handoff_requested_at', 'knitting_handoff_requested_by']);
        });
    }
};
