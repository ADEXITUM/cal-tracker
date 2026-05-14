import type { FoodServing } from '@/types/api'

export interface MealPayloadFromServing {
  name: string
  kcal: number
  proteinG: number
  fatG: number
  carbsG: number
}

/**
 * Из выбранной FatSecret-порции и множителя «сколько таких порций съели» собрать
 * KБЖУ для meal-записи. FatSecret отдаёт значения per-serving (а не per-100g),
 * поэтому достаточно умножить на $count.
 *
 * Округляем до целых для ккал/Б/Ж/У — БД и UI всё равно работают с целыми граммами.
 */
export function servingToMealPayload(
  name: string,
  serving: FoodServing,
  count: number,
): MealPayloadFromServing {
  const c = Number.isFinite(count) && count > 0 ? count : 1
  return {
    name,
    kcal: Math.round(serving.kcal * c),
    proteinG: Math.round(serving.proteinG * c * 10) / 10,
    fatG: Math.round(serving.fatG * c * 10) / 10,
    carbsG: Math.round(serving.carbsG * c * 10) / 10,
  }
}

/**
 * Короткая подпись для строки в списке порций: «1 cup (240 г) · 130 ккал».
 * Если FatSecret не предоставил граммовый эквивалент — не пишем граммы.
 */
export function formatServingLine(s: FoodServing): string {
  const head = s.grams != null
    ? `${s.description} (${formatNumber(s.grams)} г)`
    : s.description
  return `${head} · ${Math.round(s.kcal)} ккал`
}

function formatNumber(n: number): string {
  return Number.isInteger(n) ? String(n) : String(Math.round(n * 10) / 10)
}
