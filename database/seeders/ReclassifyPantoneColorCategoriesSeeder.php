<?php

namespace Database\Seeders;

use App\Models\Man\PantoneReference;
use Illuminate\Database\Seeder;

class ReclassifyPantoneColorCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $counts = ['white' => 0, 'light' => 0, 'dark' => 0];
        $whiteExamples = [];

        PantoneReference::query()->orderBy('id')->chunkById(300, function ($pantones) use (&$counts, &$whiteExamples) {
            foreach ($pantones as $pantone) {
                $hex = ltrim((string) $pantone->hex, '#');
                if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
                    continue;
                }

                [$red, $green, $blue] = sscanf($hex, '%02x%02x%02x');
                $brightness = (0.299 * $red) + (0.587 * $green) + (0.114 * $blue);
                $spread = max($red, $green, $blue) - min($red, $green, $blue);
                $nameIsWhite = preg_match('/\b(white|ivory|eggshell|egret|marshmallow|gardenia|pristine)\b|cloud dancer|blanc de blanc|whisper white|white asparagus/i', (string) $pantone->name) === 1;
                $swatchIsWhite = $brightness >= 238 && min($red, $green, $blue) >= 232 && $spread <= 35;

                $category = ($nameIsWhite || $swatchIsWhite)
                    ? 'white'
                    : ($brightness >= 145 ? 'light' : 'dark');

                if ($pantone->color_category !== $category) {
                    $pantone->color_category = $category;
                    $pantone->save();
                }

                $counts[$category]++;
                if ($category === 'white' && count($whiteExamples) < 12) {
                    $whiteExamples[] = $pantone->name;
                }
            }
        });

        $this->command?->info('Pantone category totals: '.$counts['white'].' white, '.$counts['light'].' light, '.$counts['dark'].' dark.');
        $this->command?->line('White Pantone examples: '.implode(', ', $whiteExamples));
    }
}
