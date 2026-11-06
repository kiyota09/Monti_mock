<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $materials = [
        'Sodium Sulfite Oxygen Scavenger',
        'Sodium Bisulfite Oxygen Scavenger',
        'Sodium Phosphate Boiler Treatment',
        'Trisodium Phosphate Boiler Treatment',
        'Disodium Phosphate Boiler Treatment',
        'Sodium Hydroxide Alkalinity Booster',
        'Sodium Carbonate Alkalinity Booster',
        'Boiler Scale Inhibitor',
        'Boiler Sludge Conditioner',
        'Boiler Water Polymer Dispersant',
        'Boiler Water Chelant',
        'Condensate Line Corrosion Inhibitor',
        'Neutralizing Amine Condensate Treatment',
        'Filming Amine Condensate Treatment',
        'Boiler Water Antifoam',
        'Boiler Descaler',
        'Boiler Water Alkalinity Test Reagent',
        'Boiler Water Phosphate Test Reagent',
        'Boiler Water Sulfite Test Reagent',
        'Boiler Water pH Buffer',
    ];

    public function up(): void
    {
        Schema::table('materials', function ($table) {
            $table->string('category')->change();
        });

        DB::transaction(function () {
            foreach ($this->materials as $name) {
                if (DB::table('materials')->where('name', $name)->where('category', 'Boiler Chemicals')->exists()) {
                    continue;
                }

                $lastId = DB::table('materials')->orderByDesc('id')->value('mat_id');
                $nextNumber = max(1, (int) substr((string) $lastId, 4) + 1);
                DB::table('materials')->insert([
                    'mat_id' => 'MAT-' . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT),
                    'name' => $name,
                    'category' => 'Boiler Chemicals',
                    'unit' => 'Kg',
                    'reorder_point' => 0,
                    'unit_cost' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('materials')->where('category', 'Boiler Chemicals')->whereIn('name', $this->materials)->delete();
    }
};
