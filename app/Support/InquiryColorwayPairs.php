<?php

namespace App\Support;

class InquiryColorwayPairs
{
    /** Return the explicitly requested product/Pantone/yarn combinations. */
    public static function fromSelections(array $selections, ?int $fallbackProductId = null): array
    {
        $rows = $selections['products'] ?? [];
        if (! is_array($rows) || $rows === []) {
            $single = $selections;
            $single['product_id'] = $single['product_id'] ?? $fallbackProductId;
            $rows = [$single];
        }

        $pairs = [];
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $pantones = collect($row['pantones'] ?? [])->keyBy(fn ($pantone) => (int) ($pantone['id'] ?? 0));
            $yarns = collect($row['yarns'] ?? [])->keyBy(fn ($yarn) => (int) ($yarn['id'] ?? 0));
            foreach ($pantones as $pantoneId => $pantone) {
                foreach ($yarns as $yarnId => $yarn) {
                    self::put($pairs, $productId, (int) $pantoneId, (int) $yarnId);
                }
            }
            foreach ($row['colorways'] ?? [] as $colorway) {
                $pantoneId = (int) ($colorway['pantone']['id'] ?? $colorway['pantone_id'] ?? 0);
                foreach ($colorway['yarn_ids'] ?? [] as $yarnId) {
                    $yarnId = (int) $yarnId;
                    if ($pantoneId && $yarnId && $yarns->has($yarnId)) {
                        self::put($pairs, $productId, $pantoneId, $yarnId);
                    }
                }
            }
        }

        return array_values($pairs);
    }

    private static function put(array &$pairs, int $productId, int $pantoneId, int $yarnId): void
    {
        if ($productId && $pantoneId && $yarnId) {
            $pairs[$productId.':'.$pantoneId.':'.$yarnId] = [
                'product_id' => $productId,
                'pantone_id' => $pantoneId,
                'yarn_id' => $yarnId,
            ];
        }
    }
}
