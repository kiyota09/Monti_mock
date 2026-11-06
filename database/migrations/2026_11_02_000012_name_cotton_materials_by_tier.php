<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const COTTON_NAMES_BY_TIER = [
        'Ultra Premium' => 'Egyptian Cotton',
        'Premium' => 'Pima Cotton',
        'Standard' => 'Upland Cotton',
        'Economy' => 'Open-End Cotton',
        'Recycled' => 'Recycled Cotton',
    ];

    public function up(): void
    {
        foreach (self::COTTON_NAMES_BY_TIER as $tier => $name) {
            DB::table('materials')
                ->where('category', 'Yarn')
                ->where('name', 'Cotton')
                ->where('yarn_tier', $tier)
                ->update(['name' => $name]);
        }
    }

    public function down(): void
    {
        foreach (self::COTTON_NAMES_BY_TIER as $tier => $name) {
            DB::table('materials')
                ->where('category', 'Yarn')
                ->where('name', $name)
                ->where('yarn_tier', $tier)
                ->update(['name' => 'Cotton']);
        }
    }
};
