<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $yarns = DB::table('materials')
            ->where('category', 'Yarn')
            ->orderBy('id')
            ->get(['id']);

        $materials = DB::table('materials')->orderBy('id')->get(['id', 'mat_id', 'name', 'category', 'unit']);
        $groups = [
            'dye_stuffs' => $materials->where('category', 'Dye Stuff')->values(),
            'auxiliary_agents' => $materials->where('category', 'Auxillary Agent')->values(),
            'basic_chemicals' => $materials->where('category', 'Basic Chemicals')->values(),
            'post_treatment' => $materials->where('category', 'Post-Treatment')->values(),
        ];

        foreach ($groups as $group => $items) {
            if ($items->isEmpty()) {
                throw new RuntimeException("Cannot seed all-yarn Pantone formulas: no materials exist for {$group}.");
            }
        }

        foreach (DB::table('pantone_references')->orderBy('id')->pluck('id') as $pantoneId) {
            $existingYarnIds = DB::table('pantone_fabric_formulas')
                ->where('pantone_reference_id', $pantoneId)
                ->pluck('yarn_material_id')
                ->all();

            foreach ($yarns->whereNotIn('id', $existingYarnIds) as $yarn) {
                $seed = ($pantoneId * 37) + ($yarn->id * 19);
                $combinations = [];

                foreach ($groups as $group => $available) {
                    $count = $group === 'dye_stuffs' ? 3 : ($group === 'post_treatment' ? 1 : 2);
                    $start = $seed % $available->count();
                    $rows = [];

                    for ($index = 0; $index < min($count, $available->count()); $index++) {
                        $material = $available[($start + ($index * 7)) % $available->count()];
                        $maxHundredths = $group === 'dye_stuffs' ? 14 : 6;
                        $amountHundredths = (($seed + ($index * 13) + crc32($group)) % $maxHundredths) + 2;

                        $rows[] = [
                            'material_id' => $material->id,
                            'mat_id' => $material->mat_id,
                            'name' => $material->name,
                            'amount' => number_format($amountHundredths / 100, 2, '.', ''),
                            'unit' => 'g',
                        ];
                    }

                    $combinations[$group] = $rows;
                }

                DB::table('pantone_fabric_formulas')->insertOrIgnore([
                    'pantone_reference_id' => $pantoneId,
                    'yarn_material_id' => $yarn->id,
                    'combinations' => json_encode($combinations),
                    'is_demo_data' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep seeded formulas so any later adjustments are retained.
    }
};
