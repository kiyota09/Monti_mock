<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Allow MontiTextile to maintain MULTIPLE active sites
     * (HQ, warehouses, second branch, etc.) instead of a single pin.
     */
    public function up(): void
    {
        Schema::table('ceo_locations', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('range_radius');
        });

        // Backfill: keep only the latest pin per user active so old
        // archive logs don't all become valid geofence zones at once.
        // The user can re-activate any site from the IT Geolocation page.
        try {
            $latestIds = DB::table('ceo_locations')
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('user_id')
                ->pluck('id');

            if ($latestIds->isNotEmpty()) {
                DB::table('ceo_locations')
                    ->whereNotIn('id', $latestIds)
                    ->update(['is_active' => false]);
            }
        } catch (\Exception $e) {
            // Non-fatal backfill — column default already applied.
        }
    }

    public function down(): void
    {
        Schema::table('ceo_locations', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
