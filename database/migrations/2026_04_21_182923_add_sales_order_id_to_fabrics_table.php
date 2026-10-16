<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a fabric to the specific Sales Order / Job Order it was produced for.
     * Set when the knitting operator marks a JO as done and selects which fabrics belong to it.
     * Nullable so fabrics recorded before linking remain valid.
     */
    public function up(): void
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_order_id')
                ->nullable()
                ->after('manufacturing_order_id');

            $table->foreign('sales_order_id')
                ->references('id')
                ->on('sales_orders')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->dropForeign(['sales_order_id']);
            $table->dropColumn('sales_order_id');
        });
    }
};