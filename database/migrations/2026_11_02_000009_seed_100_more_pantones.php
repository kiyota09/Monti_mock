<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $colors = [
            ['13-0513 TCX Frozen Dew', '#D8CFB2'],
            ['13-1008 TCX Bleached Sand', '#DACCB4'],
            ['14-1112 TCX Pebble', '#CAB698'],
            ['16-0924 TCX Croissant', '#C4AB86'],
            ['16-1010 TCX Incense', '#AF9A7E'],
            ['16-1315 TCX Cornstalk', '#A9947A'],
            ['17-1320 TCX Tannin', '#A68A6D'],
            ['18-1142 TCX Leather Brown', '#97572B'],
            ['18-1154 TCX Glazed Ginger', '#91552B'],
            ['16-1328 TCX Sandstone', '#C48A69'],
            ['16-1439 TCX Caramel', '#C37C54'],
            ['17-1147 TCX Amber Brown', '#A66646'],
            ['18-1239 TCX Sierra', '#985C41'],
            ['18-1244 TCX Gingerbread', '#8C4A2F'],
            ['14-0636 TCX Muted Lime', '#D1C87C'],
            ['13-0632 TCX Endive', '#D2CC81'],
            ['13-0720 TCX Custard', '#E5D68E'],
            ['12-0633 TCX Canary Yellow', '#DFD87E'],
            ['12-0738 TCX Yellow Cream', '#EFDC75'],
            ['13-0739 TCX Cream Gold', '#DEC05F'],
            ['14-0957 TCX Spectra Yellow', '#F7B718'],
            ['14-0936 TCX Sahara Sun', '#DFC08A'],
            ['14-1038 TCX New Wheat', '#D7B57F'],
            ['13-0932 TCX Cornsilk', '#EDC373'],
            ['14-0847 TCX Buff Yellow', '#F1BF70'],
            ['13-0940 TCX Sunset Gold', '#F7C46C'],
            ['16-1364 TCX Vibrant Orange', '#FF7420'],
            ['16-1360 TCX Nectarine', '#FF8656'],
            ['16-1349 TCX Coral Rose', '#F3774D'],
            ['16-1361 TCX Carrot', '#FD6F3B'],
            ['16-1452 TCX Firecracker', '#F36944'],
            ['17-1464 TCX Red Orange', '#F05627'],
            ['16-1451 TCX Nasturtium', '#FE6347'],
            ['17-1547 TCX Emberglow', '#EA6759'],
            ['17-1544 TCX Burnt Sienna', '#C65D52'],
            ['17-1553 TCX Paprika', '#CE4D42'],
            ['18-1454 TCX Red Clay', '#C2452D'],
            ['18-1555 TCX Molten Lava', '#B5332E'],
            ['17-1663 TCX Bittersweet', '#D93744'],
            ['13-2010 TCX Orchid Pink', '#F3BBCA'],
            ['13-2006 TCX Almond Blossom', '#F5BEC7'],
            ['14-1909 TCX Coral Blush', '#E6B2B8'],
            ['14-1911 TCX Candy Pink', '#F5B0BD'],
            ['15-1816 TCX Peony', '#ED9CA8'],
            ['15-1912 TCX Sea Pink', '#DE98AB'],
            ['16-2215 TCX Cashmere Rose', '#CE879F'],
            ['17-1818 TCX Red Violet', '#AD6D7F'],
            ['17-1612 TCX Mellow Mauve', '#996378'],
            ['17-1710 TCX Bordeaux', '#96637B'],
            ['18-1720 TCX Violet Quartz', '#8B4963'],
            ['18-1716 TCX Damson', '#854C65'],
            ['19-2410 TCX Amaranth', '#6F3C56'],
            ['15-1906 TCX Zephyr', '#C89FA5'],
            ['13-3405 TCX Lilac Snow', '#E0C7D7'],
            ['14-3206 TCX Winsome Orchid', '#D4B9CB'],
            ['15-3508 TCX Fair Orchid', '#C0AAC0'],
            ['15-3507 TCX Lavender Frost', '#BDABBE'],
            ['14-3710 TCX Orchid Petal', '#BFB4CB'],
            ['14-3812 TCX Pastel Lilac', '#BDB0D0'],
            ['14-3612 TCX Orchid Bloom', '#C5AECF'],
            ['16-3911 TCX Lavender Aura', '#9F99AA'],
            ['17-3917 TCX Stonewash', '#74809A'],
            ['19-3919 TCX Nightshadow Blue', '#4E5368'],
            ['19-3928 TCX Blue Indigo', '#49516D'],
            ['19-3915 TCX Graystone', '#4D495B'],
            ['19-3926 TCX Crown Blue', '#464B65'],
            ['19-3935 TCX Deep Cobalt', '#404466'],
            ['15-4105 TCX Angel Falls', '#A3BDD3'],
            ['19-3810 TCX Eclipse', '#343148'],
            ['19-4025 TCX Mood Indigo', '#353A4C'],
            ['19-3920 TCX Peacoat', '#2B2E43'],
            ['19-3921 TCX Black Iris', '#2B3042'],
            ['19-4024 TCX Dress Blues', '#2A3244'],
            ['19-4023 TCX Blue Nights', '#363B48'],
            ['13-5306 TCX Icy Morn', '#B0D3D1'],
            ['14-4810 TCX Canal Blue', '#9CC2C5'],
            ['13-5309 TCX Pastel Turquoise', '#99C5C4'],
            ['15-5209 TCX Aqua Haze', '#87B9BC'],
            ['15-4715 TCX Aqua Sea', '#6BAAAE'],
            ['16-5121 TCX Meadowbrook', '#60A0A3'],
            ['12-5505 TCX Glacier', '#C3DBD4'],
            ['17-5126 TCX Viridian Green', '#009499'],
            ['18-4930 TCX Tropical Green', '#008786'],
            ['17-5025 TCX Navigate', '#008583'],
            ['17-5029 TCX Deep Peacock Blue', '#008381'],
            ['17-5034 TCX Lapis', '#008684'],
            ['15-5519 TCX Turquoise', '#45B5AA'],
            ['15-5516 TCX Waterfall', '#3AB0A2'],
            ['18-6114 TCX Myrtle', '#4F6B58'],
            ['18-5616 TCX Posy Green', '#325B51'],
            ['19-5920 TCX Pine Needle', '#334D41'],
            ['17-6212 TCX Sea Spray', '#717E6F'],
            ['18-6011 TCX Duck Green', '#53665C'],
            ['18-5622 TCX Frosty Spruce', '#578270'],
            ['18-5621 TCX Fir', '#3A725F'],
            ['14-6319 TCX Meadow', '#8BBA94'],
            ['16-6324 TCX Jade Sheen', '#77A276'],
            ['13-0117 TCX Green Ash', '#A0DAA9'],
            ['14-0127 TCX Greengage', '#8BC28C'],
            ['15-6120 TCX Ming', '#7CB08A'],
        ];

        $yarnChoices = [
            ['Cotton', 'Standard'],
            ['Polyester', 'Standard'],
            ['Viscose Yarn', 'Standard'],
            ['Wool', 'Economy'],
            ['Nylon Yarn', 'Standard'],
        ];
        $yarns = collect($yarnChoices)->map(fn ($choice) => DB::table('materials')
            ->where('category', 'Yarn')->where('name', $choice[0])->where('yarn_tier', $choice[1])->first(['id', 'name', 'yarn_tier']))
            ->filter()->values();

        $materials = DB::table('materials')->orderBy('id')->get(['id', 'mat_id', 'name', 'category', 'unit']);
        $groups = [
            'dye_stuffs' => $materials->where('category', 'Dye Stuff')->values(),
            'auxiliary_agents' => $materials->where('category', 'Auxillary Agent')->values(),
            'basic_chemicals' => $materials->where('category', 'Basic Chemicals')->values(),
            'post_treatment' => $materials->where('category', 'Post-Treatment')->values(),
        ];

        foreach ($colors as $index => [$name, $hex]) {
            if (DB::table('pantone_references')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
                continue;
            }

            $pantoneId = DB::table('pantone_references')->insertGetId([
                'name' => $name,
                'hex' => $hex,
                'combinations' => json_encode([]),
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($yarns as $yarn) {
                $seed = ($pantoneId * 23) + ($yarn->id * 7);
                $combinations = [];
                $counts = [
                    'dye_stuffs' => 2 + ($seed % 2),
                    'auxiliary_agents' => 2,
                    'basic_chemicals' => 2,
                    'post_treatment' => 1 + (($seed >> 2) % 2),
                ];

                foreach ($counts as $group => $count) {
                    $available = $groups[$group];
                    if ($available->isEmpty()) {
                        $combinations[$group] = [];
                        continue;
                    }
                    $start = $seed % $available->count();
                    $rows = [];

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

                DB::table('pantone_fabric_formulas')->insert([
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
        // Seeded reference formulas may have been edited; retain records on rollback.
    }
};
