<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Reference formulas are for a small lab-dip swatch, not production batches.
        // Keep each ingredient dose below 0.15 g per item and label the dose in grams,
        // regardless of the inventory unit used for bulk stock tracking.
        $ranges = [
            'dye_stuffs' => [0.01, 0.15],
            'auxiliary_agents' => [0.01, 0.08],
            'basic_chemicals' => [0.01, 0.08],
            'post_treatment' => [0.01, 0.08],
        ];

        foreach (DB::table('pantone_fabric_formulas')->where('is_demo_data', true)->orderBy('id')->get() as $formula) {
            $combinations = json_decode($formula->combinations, true) ?: [];

            foreach ($ranges as $group => [$minimum, $maximum]) {
                foreach (($combinations[$group] ?? []) as $index => &$ingredient) {
                    // Deterministic small values keep the sample formulas readable and
                    // make reruns unnecessary; these are reference quantities only.
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
        // The previous dummy amounts are not restored because formulas may be edited.
    }
};
