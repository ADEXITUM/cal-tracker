import type { ModeCode } from '@/types/api'

export interface Mode {
  code: ModeCode
  label: string
  deltaKcal: number
}

export function classifyMode(goalKcal: number, tdeeKcal: number): Mode {
  const delta = goalKcal - tdeeKcal
  let code: ModeCode
  let label: string
  if (delta < -600) { code = 'extreme_cut'; label = 'Экстрим-сушка' }
  else if (delta < -300) { code = 'cut'; label = 'Сушка' }
  else if (delta < -100) { code = 'cut_lite'; label = 'Лёгкая сушка' }
  else if (delta <= 100) { code = 'maintenance'; label = 'Поддержка' }
  else if (delta <= 300) { code = 'light_bulk'; label = 'Лёгкий набор' }
  else { code = 'bulk'; label = 'Набор' }
  return { code, label, deltaKcal: delta }
}

export const MODE_DESCRIPTIONS: Record<ModeCode, string> = {
  extreme_cut: 'Очень большой дефицит >25%. Ок на 4 недели максимум. На длинной дистанции даёт срывы и потерю мышц. Рекомендую увеличить ккал.',
  cut: 'Средний дефицит. Безопасно 6-8 недель, потом diet break 1-2 недели. Прогресс на весах через 2-3 недели.',
  cut_lite: 'Небольшой дефицит. Медленнее но проще держать долго (3+ месяцев). Хорошо для рекомпозиции.',
  maintenance: 'Калории около нормы. Стабилизация веса. Идеально для diet break или образа жизни.',
  light_bulk: 'Небольшой профицит. Медленный набор массы с минимумом жира.',
  bulk: 'Профицит для активного набора. Часть прибавки — жир, это нормально. После 3-4 месяцев — на сушку.',
}

export const PRESET_DEFINITIONS = [
  { key: 'fast_cut', label: 'Быстрая сушка', deltaFromTdee: -500, expectedMode: 'cut' as ModeCode },
  { key: 'slow_cut', label: 'Медленная сушка', deltaFromTdee: -250, expectedMode: 'cut_lite' as ModeCode },
  { key: 'maintenance', label: 'Поддержка', deltaFromTdee: 0, expectedMode: 'maintenance' as ModeCode },
  { key: 'light_bulk', label: 'Лёгкий набор', deltaFromTdee: 200, expectedMode: 'light_bulk' as ModeCode },
  { key: 'bulk', label: 'Набор', deltaFromTdee: 400, expectedMode: 'bulk' as ModeCode },
] as const

export type PresetKey = typeof PRESET_DEFINITIONS[number]['key']

export interface MacroSplit {
  kcal: number
  proteinG: number
  fatG: number
  carbsG: number
}

/**
 * Default macro split:
 *   protein = 1.8 g per kg of bodyweight
 *   fat     = 25% of total kcal (1g fat = 9 kcal)
 *   carbs   = remainder
 */
export function defaultMacroSplit(targetKcal: number, weightKg: number): MacroSplit {
  const proteinG = Math.round(weightKg * 1.8)
  const fatKcal = targetKcal * 0.25
  const fatG = Math.round(fatKcal / 9)
  const proteinKcal = proteinG * 4
  const remainderKcal = Math.max(0, targetKcal - fatKcal - proteinKcal)
  const carbsG = Math.round(remainderKcal / 4)
  return { kcal: targetKcal, proteinG, fatG, carbsG }
}
