import type { ActivityLevel, Gender } from '@/types/api'

export interface TdeeBreakdown {
  bmr: number
  activityKcal: number
  stepsKcal: number
  workoutsKcal: number
  total: number
}

const ACTIVITY_MULTIPLIER: Record<ActivityLevel, number> = {
  sedentary: 1.2,
  light: 1.375,
  moderate: 1.55,
  active: 1.725,
}

const STEPS_COEFFICIENT: Record<ActivityLevel, number> = {
  sedentary: 1.0,
  light: 0.7,
  moderate: 0.4,
  active: 0.2,
}

/** Rough kcal burned by walking `steps` for someone of `weightKg`,
 *  net of the baseline activity already accounted for in the multiplier
 *  (so "couch potato + 8k steps" gets a bigger bonus than "active + 8k steps"). */
export function stepsKcal(steps: number, weightKg: number, activityLevel: ActivityLevel): number {
  if (!steps || steps <= 0) return 0
  return Math.round(steps * weightKg * 0.0005 * STEPS_COEFFICIENT[activityLevel])
}

function ageFromBirthDate(birthDate: string): number {
  const birth = new Date(birthDate + 'T12:00:00')
  const now = new Date()
  let age = now.getFullYear() - birth.getFullYear()
  const m = now.getMonth() - birth.getMonth()
  if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) age--
  return age
}

export function computeTdee(input: {
  gender: Gender
  birthDate: string
  heightCm: number
  activityLevel: ActivityLevel
  weightKg: number
  steps?: number | null
  workoutsKcal?: number
}): TdeeBreakdown {
  const age = ageFromBirthDate(input.birthDate)
  const bmr = Math.round(
    input.gender === 'male'
      ? 10 * input.weightKg + 6.25 * input.heightCm - 5 * age + 5
      : 10 * input.weightKg + 6.25 * input.heightCm - 5 * age - 161,
  )

  const mult = ACTIVITY_MULTIPLIER[input.activityLevel]
  const activityKcal = Math.round(bmr * (mult - 1))

  const stepsKcalVal = stepsKcal(input.steps ?? 0, input.weightKg, input.activityLevel)

  const workoutsKcal = input.workoutsKcal ?? 0
  const total = bmr + activityKcal + stepsKcalVal + workoutsKcal

  return { bmr, activityKcal, stepsKcal: stepsKcalVal, workoutsKcal, total }
}
