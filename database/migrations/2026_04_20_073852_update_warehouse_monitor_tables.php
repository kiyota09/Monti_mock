<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Store Grid Dimensions
        Schema::table('warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouses', 'grid_rows')) {
                $table->integer('grid_rows')->default(3)->after('location');
                $table->integer('grid_cols')->default(3)->after('grid_rows');
            }
        });

        // 2. Store Box Coordinates
        Schema::table('warehouse_sections', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouse_sections', 'grid_row')) {
                $table->integer('grid_row')->nullable()->after('warehouse_id');
                $table->integer('grid_col')->nullable()->after('grid_row');
            }
        });

        // 3. Allow Stock to link directly to a Section (the "No Shelf" drag-and-drop)
        Schema::table('warehouse_stock_items', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouse_stock_items', 'section_id')) {
                $table->foreignId('section_id')->nullable()->constrained('warehouse_sections')->onDelete('set null')->after('warehouse_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['grid_rows', 'grid_cols']);
        });
        Schema::table('warehouse_sections', function (Blueprint $table) {
            $table->dropColumn(['grid_row', 'grid_col']);
        });
        Schema::table('warehouse_stock_items', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropColumn('section_id');
        });
    }
};