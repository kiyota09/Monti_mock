<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Every formula in the Pantone library is a small-swatch lab reference.
        // Keep its doses in grams even if bulk inventory is tracked in kilograms.
        $ranges = [
            'dye_stuffs' => [0.01, 0.15],
            'auxiliary_agents' => [0.01, 0.08],
            'basic_chemicals' => [0.01, 0.08],
            'post_treatment' => [0.01, 0.08],
        ];

        foreach (DB::table('pantone_fabric_formulas')->orderBy('id')->get() as $formula) {
            $combinations = json_decode($formula->combinations, true) ?: [];

            foreach ($ranges as $group => [$minimum, $maximum]) {
                if (!isset($combinations[$group]) || !is_array($combinations[$group])) {
                    continue;
                }

                foreach ($combinations[$group] as $index => &$ingredient) {
                    if (!is_array($ingredient)) {
                        continue;
                    }

                    $seed = ($formula->id * 31) + ($index * 17) + crc32($group);
                    $rangeInHundredths = (int) round(($maximum - $minimum) * 100);
                    $hundredths = ((int) ($seed % max(1, $rangeInHundredths))) + 1;
                    $ingredient['amount'] = number_format($minimum + ($hundredths / 100), 2, '.', '');
                    $ingredient['unit'] = 'g';
                }
                unset($ingredient);
            }

            DB::table('pantone_fabric_formulas')->where('id', $formula->id)->update([
                'combinations' => json_encode($combinations),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Lab-scale values are intentionally retained; previous kg amounts were unsuitable here.
    }
};
