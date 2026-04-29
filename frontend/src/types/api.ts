export type ActivityLevel = 'sedentary' | 'light' | 'moderate' | 'active'
export type Gender = 'male' | 'female'
export type ModeCode = 'extreme_cut' | 'cut' | 'cut_lite' | 'maintenance' | 'light_bulk' | 'bulk'
export type MealSlot = 'breakfast' | 'lunch' | 'snack' | 'dinner' | 'other'

export interface User {
  uuid: string
  name: string
  email: string
  avatarColor: string
  timezone: string
  hasProfile: boolean
}

export interface Profile {
  gender: Gender
  birthDate: string
  heightCm: number
  activityLevel: ActivityLevel
  tdeeKcal: number | null
}

export interface Goal {
  uuid: string
  startDate: string
  endDate: string | null
  kcal: number
  proteinG: number
  fatG: number
  carbsG: number
  note: string | null
}

export interface Meal {
  uuid: string
  slot: MealSlot
  eatenAt: string
  dishUuid: string | null
  grams: number | null
  name: string | null
  kcal: number
  proteinG: number
  fatG: number
  carbsG: number
}

export interface Measurement {
  uuid: string
  measuredAt: string
  weightKg: number
  bodyFatPct: number | null
  muscleMassKg: number | null
  bodyWaterPct: number | null
  visceralFatLevel: number | null
  boneMassKg: number | null
  proteinPct: number | null
  heartRateBpm: number | null
  source: string
}

export interface Workout {
  uuid: string
  name: string
  durationMin: number | null
  kcalBurned: number | null
  notes: string | null
}

export interface Dish {
  uuid: string
  name: string
  kcalPer100g: number
  proteinPer100g: number
  fatPer100g: number
  carbsPer100g: number
  usageCount: number
  lastUsedAt: string | null
}

export interface Totals {
  kcal: number
  proteinG: number
  fatG: number
  carbsG: number
}

export interface DayGoal {
  kcal: number
  proteinG: number
  fatG: number
  carbsG: number
}

export interface DayMode {
  code: ModeCode
  label: string
  deltaKcal: number
}

export interface DayTdee {
  bmr: number
  activityKcal: number
  total: number
}

export interface DayEntry {
  mood: number | null
  wellbeing: number | null
  sleepHours: number | null
  steps: number | null
  notes: string | null
}

export interface DayResource {
  date: string
  dayEntry: DayEntry | null
  goal: DayGoal | null
  tdee: DayTdee | null
  mode: DayMode | null
  totals: Totals
  meals: Meal[]
  measurements: Measurement[]
  workouts: Workout[]
  insights: unknown[]
}

export interface SavedAccount {
  uuid: string
  email: string
  name: string
  avatarColor: string
  token: string
  lastUsedAt: number
}
