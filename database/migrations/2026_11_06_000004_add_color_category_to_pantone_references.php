<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pantone_references', function (Blueprint $table) {
            $table->string('color_category', 20)->default('light')->after('hex');
        });

        DB::table('pantone_references')->select(['id', 'hex'])->orderBy('id')->chunkById(500, function ($pantones) {
            foreach ($pantones as $pantone) {
                [$red, $green, $blue] = sscanf(ltrim((string) $pantone->hex, '#'), '%02x%02x%02x');
                $brightness = (0.299 * $red) + (0.587 * $green) + (0.114 * $blue);
                $spread = max($red, $green, $blue) - min($red, $green, $blue);

                $category = $red >= 235 && $green >= 235 && $blue >= 235 && $spread <= 55
                    ? 'white'
                    : ($brightness >= 145 ? 'light' : 'dark');

                DB::table('pantone_references')->where('id', $pantone->id)->update(['color_category' => $category]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pantone_references', function (Blueprint $table) {
            $table->dropColumn('color_category');
        });
    }
};
