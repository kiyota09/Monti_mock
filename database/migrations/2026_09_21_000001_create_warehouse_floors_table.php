<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Floors table — each warehouse can have N floors, each with its own grid
        Schema::create('warehouse_floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Ground Floor');
            $table->integer('level')->default(1); // 1, 2, 3... display order
            $table->integer('grid_rows')->default(3);
            $table->integer('grid_cols')->default(3);
            $table->timestamps();

            $table->unique(['warehouse_id', 'level']);
        });

        // 2. Sections belong to a floor (nullable for back-compat, filled by backfill)
        Schema::table('warehouse_sections', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouse_sections', 'floor_id')) {
                $table->foreignId('floor_id')->nullable()->after('warehouse_id')
                    ->constrained('warehouse_floors')->nullOnDelete();
            }
        });

        // 3. Backfill: every existing warehouse gets a "Ground Floor" carrying
        //    its current grid dimensions; all existing sections move onto it.
        $warehouses = DB::table('warehouses')->get();
        foreach ($warehouses as $w) {
            $floorId = DB::table('warehouse_floors')->insertGetId([
                'warehouse_id' => $w->id,
                'name' => 'Ground Floor',
                'level' => 1,
                'grid_rows' => $w->grid_rows ?? 3,
                'grid_cols' => $w->grid_cols ?? 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('warehouse_sections')
                ->where('warehouse_id', $w->id)
                ->whereNull('floor_id')
                ->update(['floor_id' => $floorId]);
        }
    }

    public function down(): void
    {
        Schema::table('warehouse_sections', function (Blueprint $table) {
            $table->dropForeign(['floor_id']);
            $table->dropColumn('floor_id');
        });
        Schema::dropIfExists('warehouse_floors');
    }
};
