<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->after('material_id')
                ->constrained('purchase_orders')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropColumn('purchase_order_id');
        });
    }
};
