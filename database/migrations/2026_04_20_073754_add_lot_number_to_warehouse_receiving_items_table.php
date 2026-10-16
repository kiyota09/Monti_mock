<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    public function up()
    {
        Schema::table('warehouse_receiving_items', function (Blueprint $table) {
            $table->string('lot_number')->nullable()->after('material_id');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_receiving_items', function (Blueprint $table) {
            //
        });
    }
};
