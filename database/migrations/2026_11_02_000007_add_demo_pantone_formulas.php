<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pantone_fabric_formulas', 'is_demo_data')) {
            Schema::table('pantone_fabric_formulas', function (Blueprint $table) {
                $table->boolean('is_demo_data')->default(false)->after('combinations');
            });
        }

        $materials = DB::table('materials')->orderBy('id')->get(['id', 'mat_id', 'name', 'category', 'unit']);
        $catalog = [
            'dye_stuffs' => $materials->filter(fn ($m) => mb_strtolower($m->category) === 'dye stuff')->values(),
            'auxiliary_agents' => $materials->filter(fn ($m) => mb_strtolower($m->category) === 'auxillary agent')->values(),
            'basic_chemicals' => $materials->filter(fn ($m) => mb_strtolower($m->category) === 'basic chemicals')->values(),
            'post_treatment' => $materials->filter(fn ($m) => mb_strtolower($m->category) === 'post-treatment')->values(),
        ];

        foreach (DB::table('pantone_fabric_formulas')->orderBy('id')->get() as $formula) {
            $seed = ($formula->pantone_reference_id * 17) + ($formula->yarn_material_id * 11);
            $counts = [
                'dye_stuffs' => 2 + ($seed % 2),
                'auxiliary_agents' => 2,
                'basic_chemicals' => 2,
                'post_treatment' => 1 + (($seed >> 2) % 2),
            ];
            $combinations = [];

            foreach ($counts as $group => $count) {
                $available = $catalog[$group];
                $rows = [];
                if ($available->isEmpty()) {
                    $combinations[$group] = $rows;
                    continue;
                }
                $start = $seed % $available->count();

                for ($i = 0; $i < min($count, $available->count()); $i++) {
                    $material = $available[($start + ($i * 7)) % $available->count()];
                    $amount = match ($group) {
                        'dye_stuffs' => number_format(0.15 + (($seed + $i * 19) % 180) / 100, 2, '.', ''),
                        'auxiliary_agents' => number_format(0.5 + (($seed + $i * 13) % 45) / 10, 2, '.', ''),
                        'basic_chemicals' => number_format(1 + (($seed + $i * 23) % 90), 2, '.', ''),
                        default => number_format(0.3 + (($seed + $i * 29) % 35) / 10, 2, '.', ''),
                    };

                    $rows[] = [
                        'material_id' => $material->id,
                        'mat_id' => $material->mat_id,
                        'name' => $material->name,
                        'amount' => $amount,
                        'unit' => $material->unit,
                    ];
                }

                $combinations[$group] = $rows;
            }

            DB::table('pantone_fabric_formulas')->where('id', $formula->id)->update([
                'combinations' => json_encode($combinations),
                'is_demo_data' => true,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('pantone_fabric_formulas')->update([
            'combinations' => json_encode([
                'dye_stuffs' => [],
                'auxiliary_agents' => [],
                'basic_chemicals' => [],
                'post_treatment' => [],
            ]),
            'is_demo_data' => false,
            'updated_at' => now(),
        ]);

        Schema::table('pantone_fabric_formulas', function (Blueprint $table) {
            $table->dropColumn('is_demo_data');
        });
    }
};
