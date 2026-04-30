import type { ActivityLevel, Gender } from '@/types/api'

export interface TdeeBreakdown {
  bmr: number
  baseKcal: number
  stepsKcal: number
  workoutsKcal: number
  total: number
}

/** Sedentary BMR multiplier — covers sleep + basic life without intentional walking/training. */
export const BASE_MULTIPLIER = 1.2

/** kcal per step per kg of bodyweight (rough average for walking pace). */
export const STEP_KCAL_PER_KG = 0.0005

export function stepsKcal(steps: number, weightKg: number): number {
  if (!steps || steps <= 0) return 0
  return Math.round(steps * weightKg * STEP_KCAL_PER_KG)
}

function ageFromBirthDate(birthDate: string): number {
  const birth = new Date(birthDate + 'T12:00:00')
  const now = new Date()
  let age = now.getFullYear() - birth.getFullYear()
  const m = now.getMonth() - birth.getMonth()
  if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) age--
  return age
}

export function bmr(input: {
  gender: Gender
  birthDate: string
  heightCm: number
  weightKg: number
}): number {
  const age = ageFromBirthDate(input.birthDate)
  return Math.round(
    input.gender === 'male'
      ? 10 * input.weightKg + 6.25 * input.heightCm - 5 * age + 5
      : 10 * input.weightKg + 6.25 * input.heightCm - 5 * age - 161,
  )
}

export function computeTdee(input: {
  gender: Gender
  birthDate: string
  heightCm: number
  weightKg: number
  steps?: number | null
  workoutsKcal?: number
}): TdeeBreakdown {
  const bmrVal = bmr(input)
  const baseKcal = Math.round(bmrVal * BASE_MULTIPLIER)
  const stepsKcalVal = stepsKcal(input.steps ?? 0, input.weightKg)
  const workoutsKcal = input.workoutsKcal ?? 0
  const total = baseKcal + stepsKcalVal + workoutsKcal

  return { bmr: bmrVal, baseKcal, stepsKcal: stepsKcalVal, workoutsKcal, total }
}

/**
 * Activity-level multipliers for the *goal calculator helper*.
 * Used only when proposing a goal kcal value during goal creation —
 * not stored on the profile, just helps the user pick a starting number.
 */
export const ACTIVITY_MULTIPLIER: Record<ActivityLevel, number> = {
  sedentary: 1.2,
  light:     1.4,
  moderate:  1.55,
  active:    1.725,
}

export const ACTIVITY_LABEL: Record<ActivityLevel, string> = {
  sedentary: 'Сидячая (без спорта)',
  light:     'Лёгкая (1-2 трен/нед)',
  moderate:  'Средняя (3-4 трен/нед)',
  active:    'Высокая (5+ трен/нед)',
}

/** Estimate "average daily TDEE" for the goal calculator. */
export function estimateAverageTdee(input: {
  gender: Gender
  birthDate: string
  heightCm: number
  weightKg: number
  activityLevel: ActivityLevel
}): number {
  return Math.round(bmr(input) * ACTIVITY_MULTIPLIER[input.activityLevel])
}
