<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Digital swatch values are screen approximations of textile references.
        $colors = [
            ['11-0601 TCX Bright White', '#F4F5F0'],
            ['11-0602 TCX Snow White', '#F1F0EA'],
            ['11-0103 TCX Egret', '#F3ECE0'],
            ['11-4300 TCX Lily White', '#E7E9E7'],
            ['11-4201 TCX Cloud Dancer', '#F0EEE9'],
            ['12-0713 TCX Golden Haze', '#F6EBC8'],
            ['13-0752 TCX Mimosa', '#F0C05A'],
            ['13-0647 TCX Evening Primrose', '#F3E779'],
            ['14-0848 TCX Mango Mojito', '#D69C2F'],
            ['15-0953 TCX Aspen Gold', '#FFD662'],
            ['16-1359 TCX Turmeric', '#FE840E'],
            ['17-1462 TCX Flame', '#F2552C'],
            ['18-1664 TCX Poppy Red', '#E94B3C'],
            ['19-1557 TCX Salsa', '#AA182B'],
            ['19-1763 TCX True Red', '#BF1932'],
            ['19-2025 TCX Beet Red', '#7A1F3D'],
            ['18-2143 TCX Raspberry', '#D2386C'],
            ['17-1937 TCX Pink Yarrow', '#CE3175'],
            ['16-1731 TCX Pink Lemonade', '#EE6D8A'],
            ['13-1404 TCX Pale Dogwood', '#EDCFCB'],
            ['13-2802 TCX Fairy Tale', '#F2C1D1'],
            ['14-1513 TCX Rosewater', '#F5D1C8'],
            ['15-1514 TCX Rose Brown', '#BC8F8F'],
            ['16-1518 TCX Ash Rose', '#B5817D'],
            ['17-1929 TCX Raspberry Sorbet', '#D2386C'],
            ['18-1756 TCX Raspberry Wine', '#B63753'],
            ['19-2434 TCX Beetroot Purple', '#CF2D71'],
            ['19-3526 TCX Purple Velvet', '#5B4349'],
            ['18-3838 TCX Ultra Violet', '#5F4B8B'],
            ['17-3938 TCX Very Peri', '#6667AB'],
            ['16-3823 TCX Violet Tulip', '#9E91C3'],
            ['15-3817 TCX Lavender Herb', '#B7A9C8'],
            ['13-3804 TCX Lavender Fog', '#D2C4D6'],
            ['14-3612 TCX Orchid Hush', '#CEC3D2'],
            ['16-3118 TCX Opera Mauve', '#CA80B1'],
            ['17-3020 TCX Fuchsia Purple', '#AD2E8A'],
            ['18-3224 TCX Byzantium', '#853B7B'],
            ['19-3410 TCX Plum', '#5A315D'],
            ['19-3952 TCX Sodalite Blue', '#253668'],
            ['19-4052 TCX Classic Blue', '#0F4C81'],
            ['18-4140 TCX Palace Blue', '#346CB0'],
            ['17-4041 TCX Marina', '#4F84C4'],
            ['16-4036 TCX Dazzling Blue', '#3850A0'],
            ['15-3919 TCX Serenity', '#91A8D0'],
            ['13-3920 TCX Halogen Blue', '#BDC6DC'],
            ['12-4608 TCX Blue Bell', '#A4C8E1'],
            ['14-4318 TCX Aqua Sky', '#7BC4C4'],
            ['15-5217 TCX Blue Turquoise', '#53B0AE'],
            ['16-5123 TCX Caribbean Sea', '#0085A1'],
            ['17-4919 TCX Teal', '#478589'],
            ['18-4528 TCX Deep Teal', '#184A45'],
            ['19-4914 TCX Deep Lake', '#476A70'],
            ['18-1750 TCX Viva Magenta', '#BB2649'],
            ['13-1023 TCX Peach Fuzz', '#FFBE98'],
            ['17-1230 TCX Mocha Mousse', '#A47764'],
        ];

        foreach ($colors as [$name, $hex]) {
            $exists = DB::table('pantone_references')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('pantone_references')->insert([
                'name' => $name,
                'hex' => $hex,
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
        }
    }

    public function down(): void
    {
        $names = [
            '11-0601 TCX Bright White', '11-0602 TCX Snow White', '11-0103 TCX Egret',
            '11-4300 TCX Lily White', '11-4201 TCX Cloud Dancer', '12-0713 TCX Golden Haze',
            '13-0752 TCX Mimosa', '13-0647 TCX Evening Primrose', '14-0848 TCX Mango Mojito',
            '15-0953 TCX Aspen Gold', '16-1359 TCX Turmeric', '17-1462 TCX Flame',
            '18-1664 TCX Poppy Red', '19-1557 TCX Salsa', '19-1763 TCX True Red',
            '19-2025 TCX Beet Red', '18-2143 TCX Raspberry', '17-1937 TCX Pink Yarrow',
            '16-1731 TCX Pink Lemonade', '13-1404 TCX Pale Dogwood', '13-2802 TCX Fairy Tale',
            '14-1513 TCX Rosewater', '15-1514 TCX Rose Brown', '16-1518 TCX Ash Rose',
            '17-1929 TCX Raspberry Sorbet', '18-1756 TCX Raspberry Wine', '19-2434 TCX Beetroot Purple',
            '19-3526 TCX Purple Velvet', '18-3838 TCX Ultra Violet', '17-3938 TCX Very Peri',
            '16-3823 TCX Violet Tulip', '15-3817 TCX Lavender Herb', '13-3804 TCX Lavender Fog',
            '14-3612 TCX Orchid Hush', '16-3118 TCX Opera Mauve', '17-3020 TCX Fuchsia Purple',
            '18-3224 TCX Byzantium', '19-3410 TCX Plum', '19-3952 TCX Sodalite Blue',
            '19-4052 TCX Classic Blue', '18-4140 TCX Palace Blue', '17-4041 TCX Marina',
            '16-4036 TCX Dazzling Blue', '15-3919 TCX Serenity', '13-3920 TCX Halogen Blue',
            '12-4608 TCX Blue Bell', '14-4318 TCX Aqua Sky', '15-5217 TCX Blue Turquoise',
            '16-5123 TCX Caribbean Sea', '17-4919 TCX Teal', '18-4528 TCX Deep Teal',
            '19-4914 TCX Deep Lake', '18-1750 TCX Viva Magenta', '13-1023 TCX Peach Fuzz',
            '17-1230 TCX Mocha Mousse',
        ];

        DB::table('pantone_references')->whereIn('name', $names)->delete();
    }
};
