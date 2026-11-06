<?php

namespace Database\Seeders;

use App\Models\Inv\Product;
use App\Models\Man\PantoneReference;
use Illuminate\Database\Seeder;
use RuntimeException;

class ReplaceLegacyProductColorsWithPantonesSeeder extends Seeder
{
    private const LEGACY_COLORS = [
        '#000000', '#ffffff', '#ef4444', '#f97316', '#eab308',
        '#22c55e', '#14b8a6', '#3b82f6', '#1e3a8a', '#8b5cf6',
    ];

    public function run(): void
    {
        $pantones = PantoneReference::query()->orderBy('id')->get(['id', 'name', 'hex']);
        if ($pantones->isEmpty()) {
            throw new RuntimeException('Cannot replace legacy product colors because the Pantone library is empty.');
        }

        $available = $pantones->filter(fn (PantoneReference $pantone) => preg_match('/^#[0-9a-f]{6}$/i', (string) $pantone->hex));
        if ($available->isEmpty()) {
            throw new RuntimeException('The Pantone library has no colors with valid hex swatches.');
        }

        $replacementByHex = [];
        $usedPantoneIds = [];
        foreach (self::LEGACY_COLORS as $legacyHex) {
            $nearest = $available
                ->reject(fn (PantoneReference $pantone) => in_array($pantone->id, $usedPantoneIds, true))
                ->sortBy(fn (PantoneReference $pantone) => $this->colorDistance($legacyHex, $pantone->hex))
                ->first();

            if (! $nearest) {
                throw new RuntimeException('Not enough distinct Pantone swatches to replace the legacy palette.');
            }

            $replacementByHex[$legacyHex] = $nearest;
            $usedPantoneIds[] = $nearest->id;
        }

        $updatedProducts = 0;
        $replacedColorways = 0;
        foreach (Product::query()->orderBy('id')->get() as $product) {
            $colors = $product->colors;
            if (is_string($colors)) {
                $colors = json_decode($colors, true) ?: [];
            }
            if (! is_array($colors) || $colors === []) {
                continue;
            }

            $changed = false;
            foreach ($colors as &$color) {
                if (! is_array($color)) {
                    continue;
                }
                $hex = strtolower((string) ($color['hex'] ?? ''));
                $pantone = $replacementByHex[$hex] ?? null;
                if (! $pantone) {
                    continue;
                }

                $color = [
                    'pantone_id' => $pantone->id,
                    'name' => $pantone->name,
                    'hex' => $pantone->hex,
                ];
                $changed = true;
                $replacedColorways++;
            }
            unset($color);

            if ($changed) {
                $product->colors = array_values($colors);
                $product->save();
                $updatedProducts++;
            }
        }

        $this->command?->info("Replaced {$replacedColorways} legacy colorways across {$updatedProducts} products with Pantone swatches.");
        foreach ($replacementByHex as $legacyHex => $pantone) {
            $this->command?->line(strtoupper($legacyHex).' → '.$pantone->name.' ('.$pantone->hex.')');
        }
    }

    private function colorDistance(string $firstHex, string $secondHex): int
    {
        $first = sscanf(substr($firstHex, 1), '%02x%02x%02x');
        $second = sscanf(substr($secondHex, 1), '%02x%02x%02x');

        return (($first[0] - $second[0]) ** 2)
            + (($first[1] - $second[1]) ** 2)
            + (($first[2] - $second[2]) ** 2);
    }
}
