import type { GoalType, ModeCode } from '@/types/api'

export interface Mode {
  code: ModeCode
  label: string
  deltaKcal: number
}

/** Within ±5% of goal — counts as on target. */
export const ON_TARGET_PCT = 0.05
/** Within 5–15% deviation — moderate miss. */
export const MODERATE_PCT = 0.15

/**
 * Daily plan execution: how close did `eatenKcal` come to the goal.
 * The goal type itself (cut/maintenance/bulk) is a separate user choice.
 */
export function classifyMode(goalKcal: number, eatenKcal: number): Mode {
  const delta = Math.round(eatenKcal - goalKcal)
  const pct = goalKcal > 0 ? Math.abs(delta) / goalKcal : 0

  let code: ModeCode
  let label: string
  if (pct <= ON_TARGET_PCT) { code = 'on_target'; label = 'На цели' }
  else if (delta > 0 && pct <= MODERATE_PCT) { code = 'over'; label = 'Перебор' }
  else if (delta > 0) { code = 'far_over'; label = 'Сильный перебор' }
  else if (pct <= MODERATE_PCT) { code = 'under'; label = 'Недобор' }
  else { code = 'far_under'; label = 'Сильный недобор' }
  return { code, label, deltaKcal: delta }
}

export const MODE_DESCRIPTIONS: Record<ModeCode, string> = {
  on_target: 'Калории в цель ±5%. Так и держим — это и есть выполнение плана.',
  over:      'Перебор по калориям 5–15%. Один день — не страшно, главное чтоб не каждый день.',
  far_over:  'Перебор больше 15%. Стоит проверить размеры порций или добавить движения.',
  under:     'Недобор 5–15%. Проверь — реально не голоден или просто забыл записать?',
  far_under: 'Недобор больше 15%. Долго так нельзя — метаболизм замедлится.',
}

/** Color for heatmap based on mode code — green ok, yellow warning, red bad. */
export function modeColor(code: ModeCode | null | undefined): string {
  if (!code) return 'var(--color-surface-2)'
  if (code === 'on_target') return 'var(--color-accent)'
  if (code === 'over' || code === 'under') return 'var(--color-yellow)'
  return 'var(--color-red)'
}

export const GOAL_TYPE_LABEL: Record<GoalType, string> = {
  cut:         'Сушка',
  maintenance: 'Поддержка',
  bulk:        'Набор',
}

export const GOAL_TYPE_DESCRIPTION: Record<GoalType, string> = {
  cut:         'Дефицит калорий, цель — снижение веса/жира',
  maintenance: 'Калории около нормы, цель — стабильный вес',
  bulk:        'Профицит калорий, цель — набор массы',
}

/** Suggested kcal delta from average TDEE for each goal type (used in preset calc). */
export const GOAL_TYPE_DELTA: Record<GoalType, number> = {
  cut:         -400,
  maintenance: 0,
  bulk:        +300,
}

export interface MacroSplit {
  kcal: number
  proteinG: number
  fatG: number
  carbsG: number
}

/**
 * Default macro split:
 *   protein = 1.8 g per kg of bodyweight
 *   fat     = 25% of total kcal
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
