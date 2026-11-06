<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sourcePath = database_path('seeders/data/pantone_fhi_color_reference.json');
        $source = json_decode(file_get_contents($sourcePath), true, flags: JSON_THROW_ON_ERROR);
        $yarns = DB::table('materials')->where('category', 'Yarn')->orderBy('id')->get(['id']);

        if ($yarns->isEmpty()) {
            throw new RuntimeException('Cannot add Pantone references without Yarn materials.');
        }

        $materials = DB::table('materials')->orderBy('id')->get(['id', 'mat_id', 'name', 'category']);
        $groups = [
            'dye_stuffs' => $materials->where('category', 'Dye Stuff')->values(),
            'auxiliary_agents' => $materials->where('category', 'Auxillary Agent')->values(),
            'basic_chemicals' => $materials->where('category', 'Basic Chemicals')->values(),
            'post_treatment' => $materials->where('category', 'Post-Treatment')->values(),
        ];

        foreach ($groups as $group => $items) {
            if ($items->isEmpty()) {
                throw new RuntimeException("Cannot seed Pantone formulas: no materials exist for {$group}.");
            }
        }

        $added = 0;
        foreach ($source as $code => $reference) {
            if ($added >= 900) {
                break;
            }

            $code = strtoupper(trim((string) $code));
            $colorName = ucwords(str_replace('-', ' ', (string) ($reference['name'] ?? '')));
            $hex = strtoupper((string) ($reference['hex'] ?? ''));

            if (!preg_match('/^\d{2}-\d{4}$/', $code) || $colorName === '' || !preg_match('/^[0-9A-F]{6}$/', $hex)) {
                continue;
            }

            // Keep only colors not already in the Pantone library.
            $alreadyStored = DB::table('pantone_references')
                ->where('name', 'like', $code.'%')
                ->exists();
            if ($alreadyStored) {
                continue;
            }

            $pantoneId = DB::table('pantone_references')->insertGetId([
                'name' => $code.' TCX '.$colorName,
                'hex' => '#'.$hex,
                'combinations' => json_encode([
                    'dye_stuffs' => [],
                    'auxiliary_agents' => [],
                    'basic_chemicals' => [],
                    'post_treatment' => [],
                ]),
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $formulaRows = [];
            foreach ($yarns as $yarn) {
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

                $formulaRows[] = [
                    'pantone_reference_id' => $pantoneId,
                    'yarn_material_id' => $yarn->id,
                    'combinations' => json_encode($combinations),
                    'is_demo_data' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($formulaRows, 100) as $formulaChunk) {
                DB::table('pantone_fabric_formulas')->insert($formulaChunk);
            }

            $added++;
        }

        if ($added !== 900) {
            throw new RuntimeException("Expected 900 new Pantone colors, but only {$added} distinct references were available.");
        }
    }

    public function down(): void
    {
        // Keep references and formula records so subsequent lab adjustments are not lost.
    }
};
