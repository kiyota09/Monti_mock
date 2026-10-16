<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('manufacturing_orders', function (Blueprint $table) {
            // 1. Find and drop the existing foreign key for purchase_order_id
            $pkConstraint = null;
            $result = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'manufacturing_orders' 
                AND COLUMN_NAME = 'purchase_order_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            if (!empty($result)) {
                $pkConstraint = $result[0]->CONSTRAINT_NAME;
                $table->dropForeign($pkConstraint);
            }

            // 2. Make purchase_order_id nullable
            $table->unsignedBigInteger('purchase_order_id')->nullable()->change();

            // 3. Re-add foreign key for purchase_order_id
            $table->foreign('purchase_order_id')
                  ->references('id')
                  ->on('purchase_orders')
                  ->onDelete('cascade');

            // 4. Ensure foreign key for sales_order_id exists (column already there)
            $skConstraint = null;
            $result2 = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'manufacturing_orders' 
                AND COLUMN_NAME = 'sales_order_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            if (empty($result2)) {
                $table->foreign('sales_order_id')
                      ->references('id')
                      ->on('sales_orders')
                      ->onDelete('cascade');
            }
        });
    }

    public function down()
    {
        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->unsignedBigInteger('purchase_order_id')->nullable(false)->change();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders');
            
            // Drop foreign key for sales_order_id if exists
            $table->dropForeign(['sales_order_id']);
        });
    }
};