<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Default macro split.
 *
 * Why these numbers:
 *  - Protein 1.8 g/kg — middle of the 1.6–2.2 g/kg range that protects lean
 *    mass during a deficit and supports growth in a surplus.
 *  - Fat 25% of total kcal — minimum healthy level (~0.8 g/kg) without
 *    crowding out protein/carbs.
 *  - Carbs — whatever kcal remain.
 */
final class Macros
{
    public const PROTEIN_G_PER_KG = 1.8;
    public const FAT_RATIO        = 0.25;

    /** @return array{kcal:int, protein_g:int, fat_g:int, carbs_g:int} */
    public static function defaultSplit(int $targetKcal, float $weightKg): array
    {
        $proteinG = (int) round($weightKg * self::PROTEIN_G_PER_KG);
        $fatKcal  = $targetKcal * self::FAT_RATIO;
        $fatG     = (int) round($fatKcal / Numbers::KCAL_PER_FAT_G);

        $proteinKcal   = $proteinG * Numbers::KCAL_PER_PROTEIN_G;
        $remainderKcal = max(0, $targetKcal - (int) round($fatKcal) - $proteinKcal);
        $carbsG        = (int) round($remainderKcal / Numbers::KCAL_PER_CARB_G);

        return [
            'kcal'      => $targetKcal,
            'protein_g' => $proteinG,
            'fat_g'     => $fatG,
            'carbs_g'   => $carbsG,
        ];
    }
}
