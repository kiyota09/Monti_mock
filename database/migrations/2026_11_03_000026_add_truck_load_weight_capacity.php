<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->decimal('max_load_kg', 12, 2)->nullable()->after('max_packages');
        });
        Schema::table('warehouse_packages', function (Blueprint $table) {
            $table->decimal('weight_kg', 12, 2)->nullable()->after('quantity');
        });

        // Existing manufacturing packages map back to the fabric roll whose
        // measured weight they contain. Keep unknown legacy rows unset so
        // Dispatch cannot silently treat a package count as kilograms.
        $legacy = DB::table('warehouse_packages as wp')
            ->join('packages as p', 'p.code', '=', 'wp.package_number')
            ->join('fabrics as f', 'f.id', '=', 'p.fabric_id')
            ->whereNull('wp.weight_kg')
            ->select('wp.id', 'f.weight')->get();
        foreach ($legacy as $row) {
            DB::table('warehouse_packages')->where('id', $row->id)->update(['weight_kg' => $row->weight]);
        }

        $legacyGroups = DB::table('warehouse_packages as wp')
            ->join('finishing_package_groups as g', 'g.warehouse_package_id', '=', 'wp.id')
            ->join('finishing_package_group_items as gi', 'gi.finishing_package_group_id', '=', 'g.id')
            ->join('finishing_work_items as wi', 'wi.id', '=', 'gi.finishing_work_item_id')
            ->join('fabrics as f', 'f.id', '=', 'wi.fabric_id')
            ->whereNull('wp.weight_kg')->groupBy('wp.id')
            ->select('wp.id', DB::raw('SUM(f.weight) as group_weight'))->get();
        foreach ($legacyGroups as $row) {
            DB::table('warehouse_packages')->where('id', $row->id)->update(['weight_kg' => $row->group_weight]);
        }
    }

    public function down(): void
    {
        Schema::table('warehouse_packages', fn (Blueprint $table) => $table->dropColumn('weight_kg'));
        Schema::table('trucks', fn (Blueprint $table) => $table->dropColumn('max_load_kg'));
    }
};
