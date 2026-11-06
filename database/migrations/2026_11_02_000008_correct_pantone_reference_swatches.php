<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // TCX screen values are approximate sRGB representations of the cotton standards.
        $corrections = [
            '11-0601 TCX Bright White' => ['11-0601 TCX Bright White', '#F4F5F0'],
            '11-0602 TCX Snow White' => ['11-0602 TCX Snow White', '#F2F0EB'],
            '11-0103 TCX Egret' => ['11-0103 TCX Egret', '#F3ECE0'],
            '11-4300 TCX Lily White' => ['11-4300 TCX Marshmallow', '#F0EEE4'],
            '11-4201 TCX Cloud Dancer' => ['11-4201 TCX Cloud Dancer', '#F0EEE9'],
            '12-0713 TCX Golden Haze' => ['12-0826 TCX Golden Haze', '#FBD897'],
            '13-0752 TCX Mimosa' => ['14-0848 TCX Mimosa', '#F0C05A'],
            '13-0647 TCX Evening Primrose' => ['13-0651 TCX Evening Primrose', '#CCDB1E'],
            '14-0848 TCX Mango Mojito' => ['15-0960 TCX Mango Mojito', '#D69C2F'],
            '15-0953 TCX Aspen Gold' => ['13-0850 TCX Aspen Gold', '#FFD662'],
            '16-1359 TCX Turmeric' => ['15-1264 TCX Turmeric', '#FE840E'],
            '17-1462 TCX Flame' => ['17-1462 TCX Flame', '#F2552C'],
            '18-1664 TCX Poppy Red' => ['17-1664 TCX Poppy Red', '#DC343B'],
            '19-1557 TCX Salsa' => ['18-1657 TCX Salsa', '#AA182B'],
            '19-1763 TCX True Red' => ['19-1664 TCX True Red', '#BF1932'],
            '19-2025 TCX Beet Red' => ['19-2030 TCX Beet Red', '#7A1F3D'],
            '18-2143 TCX Raspberry' => ['18-1754 TCX Raspberry', '#D32E5E'],
            '17-1937 TCX Pink Yarrow' => ['17-2034 TCX Pink Yarrow', '#CE3175'],
            '16-1731 TCX Pink Lemonade' => ['16-1735 TCX Pink Lemonade', '#EE6D8A'],
            '13-1404 TCX Pale Dogwood' => ['13-1404 TCX Pale Dogwood', '#EDCDC2'],
            '13-2802 TCX Fairy Tale' => ['13-2802 TCX Fairy Tale', '#F2C1D1'],
            '14-1513 TCX Rosewater' => ['11-1408 TCX Rosewater', '#F6DBD8'],
            '15-1514 TCX Rose Brown' => ['18-1512 TCX Rose Brown', '#80565B'],
            '16-1518 TCX Ash Rose' => ['17-1514 TCX Ash Rose', '#B5817D'],
            '17-1929 TCX Raspberry Sorbet' => ['18-2043 TCX Raspberry Sorbet', '#D2386C'],
            '18-1756 TCX Raspberry Wine' => ['18-1741 TCX Raspberry Wine', '#B63753'],
            '19-2434 TCX Beetroot Purple' => ['18-2143 TCX Beetroot Purple', '#CF2D71'],
            '19-3526 TCX Purple Velvet' => ['19-3725 TCX Purple Velvet', '#41354D'],
            '18-3838 TCX Ultra Violet' => ['18-3838 TCX Ultra Violet', '#5F4B8B'],
            '17-3938 TCX Very Peri' => ['17-3938 TCX Very Peri', '#6667AB'],
            '16-3823 TCX Violet Tulip' => ['16-3823 TCX Violet Tulip', '#9E91C3'],
            '15-3817 TCX Lavender Herb' => ['15-3817 TCX Lavender', '#AFA4CE'],
            '13-3804 TCX Lavender Fog' => ['13-3820 TCX Lavender Fog', '#D2C4D6'],
            '14-3612 TCX Orchid Hush' => ['13-3805 TCX Orchid Hush', '#CEC3D2'],
            '16-3118 TCX Opera Mauve' => ['16-3116 TCX Opera Mauve', '#CA80B1'],
            '17-3020 TCX Fuchsia Purple' => ['18-2436 TCX Fuchsia Purple', '#D33479'],
            '18-3224 TCX Byzantium' => ['18-3224 TCX Radiant Orchid', '#AD5E99'],
            '19-3410 TCX Plum' => ['19-3220 TCX Plum', '#5A315D'],
            '19-3952 TCX Sodalite Blue' => ['19-3953 TCX Sodalite Blue', '#253668'],
            '19-4052 TCX Classic Blue' => ['19-4052 TCX Classic Blue', '#0F4C81'],
            '18-4140 TCX Palace Blue' => ['18-4043 TCX Palace Blue', '#346CB0'],
            '17-4041 TCX Marina' => ['17-4041 TCX Marina', '#4F84C4'],
            '16-4036 TCX Dazzling Blue' => ['18-3949 TCX Dazzling Blue', '#3850A0'],
            '15-3919 TCX Serenity' => ['15-3919 TCX Serenity', '#91A8D0'],
            '13-3920 TCX Halogen Blue' => ['13-3920 TCX Halogen Blue', '#BDC6DC'],
            '12-4608 TCX Blue Bell' => ['14-4121 TCX Blue Bell', '#93B4D7'],
            '14-4318 TCX Aqua Sky' => ['14-4811 TCX Aqua Sky', '#7BC4C4'],
            '15-5217 TCX Blue Turquoise' => ['15-5217 TCX Blue Turquoise', '#53B0AE'],
            '16-5123 TCX Caribbean Sea' => ['18-4525 TCX Caribbean Sea', '#00819D'],
            '17-4919 TCX Teal' => ['17-4919 TCX Teal', '#478589'],
            '18-4528 TCX Deep Teal' => ['19-4914 TCX Deep Teal', '#18454B'],
            '19-4914 TCX Deep Lake' => ['18-4834 TCX Deep Lake', '#00656B'],
            '18-1750 TCX Viva Magenta' => ['18-1750 TCX Viva Magenta', '#BB2649'],
            '13-1023 TCX Peach Fuzz' => ['13-1023 TCX Peach Fuzz', '#FFBE98'],
            '17-1230 TCX Mocha Mousse' => ['17-1230 TCX Mocha Mousse', '#A47864'],
        ];

        foreach ($corrections as $oldName => [$correctedName, $hex]) {
            $pantone = DB::table('pantone_references')->where('name', $oldName)->first();
            if (! $pantone) {
                continue;
            }

            $nameTaken = DB::table('pantone_references')
                ->where('name', $correctedName)
                ->where('id', '!=', $pantone->id)
                ->exists();

            DB::table('pantone_references')->where('id', $pantone->id)->update([
                'name' => $nameTaken ? $oldName : $correctedName,
                'hex' => $hex,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep corrected Pantone references intact when rolling back schema changes.
    }
};
