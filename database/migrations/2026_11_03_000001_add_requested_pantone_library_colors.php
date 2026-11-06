<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $colors = [
            ['name' => '14-4130 TCX Blue Frost', 'hex' => '#A7C3E2'],
            ['name' => '19-4027 TCX Estate Blue', 'hex' => '#233658'],
            ['name' => '15-3920 TCX Placid Blue', 'hex' => '#8CADD3'],
        ];

        foreach ($colors as $color) {
            DB::table('pantone_references')->insertOrIgnore([
                ...$color,
                'combinations' => json_encode([
                    'dye_stuffs' => [],
                    'auxiliary_agents' => [],
                    'basic_chemicals' => [],
                    'post_treatment' => [],
                ]),
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep catalog entries so formulas added by lab staff are not lost.
    }
};
