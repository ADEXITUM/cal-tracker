/**
 * Macro split formula.
 *
 * Why these numbers:
 *  - Protein 1.8 g/kg — middle of the 1.6–2.2 g/kg range that protects lean
 *    mass during a deficit and supports growth in a surplus.
 *  - Fat 25% of total kcal — minimum healthy level (~0.8 g/kg) without
 *    crowding out protein/carbs.
 *  - Carbs — whatever kcal remain.
 */

/** Atwater factors — energy yield per gram of macronutrient. */
export const KCAL_PER_PROTEIN_G = 4
export const KCAL_PER_CARB_G    = 4
export const KCAL_PER_FAT_G     = 9

export const DEFAULT_PROTEIN_G_PER_KG = 1.8
export const DEFAULT_FAT_RATIO        = 0.25

export interface MacroSplit {
  kcal: number
  proteinG: number
  fatG: number
  carbsG: number
}

export function defaultMacroSplit(targetKcal: number, weightKg: number): MacroSplit {
  const proteinG = Math.round(weightKg * DEFAULT_PROTEIN_G_PER_KG)
  const fatKcal = targetKcal * DEFAULT_FAT_RATIO
  const fatG = Math.round(fatKcal / KCAL_PER_FAT_G)
  const proteinKcal = proteinG * KCAL_PER_PROTEIN_G
  const remainderKcal = Math.max(0, targetKcal - fatKcal - proteinKcal)
  const carbsG = Math.round(remainderKcal / KCAL_PER_CARB_G)
  return { kcal: targetKcal, proteinG, fatG, carbsG }
}
