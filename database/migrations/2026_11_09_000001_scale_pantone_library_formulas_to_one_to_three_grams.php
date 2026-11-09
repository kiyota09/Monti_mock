<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const GROUPS = [
        'dye_stuffs',
        'auxiliary_agents',
        'basic_chemicals',
        'post_treatment',
    ];

    public function up(): void
    {
        DB::table('pantone_fabric_formulas')->orderBy('id')->chunkById(250, function ($formulas) {
            foreach ($formulas as $formula) {
                $combinations = json_decode($formula->combinations, true) ?: [];
                $changed = false;

                foreach (self::GROUPS as $group) {
                    if (! isset($combinations[$group]) || ! is_array($combinations[$group])) {
                        continue;
                    }

                    foreach ($combinations[$group] as $index => &$ingredient) {
                        if (! is_array($ingredient)) {
                            continue;
                        }

                        $seed = abs(crc32($formula->id.':'.$formula->yarn_material_id.':'.$group.':'.$index));
                        $amountHundredths = 100 + ($seed % 201);
                        $ingredient['amount'] = number_format($amountHundredths / 100, 2, '.', '');
                        $ingredient['unit'] = 'g';
                        $changed = true;
                    }
                    unset($ingredient);
                }

                if ($changed) {
                    DB::table('pantone_fabric_formulas')->where('id', $formula->id)->update([
                        'combinations' => json_encode($combinations),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Previous ingredient amounts cannot be restored after user formulas are rescaled.
    }
};
