export type ActivityLevel = 'sedentary' | 'light' | 'moderate' | 'active'
export type Gender = 'male' | 'female'
export type ModeCode = 'extreme_cut' | 'cut' | 'cut_lite' | 'maintenance' | 'light_bulk' | 'bulk'

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

export interface SavedAccount {
  uuid: string
  email: string
  name: string
  avatarColor: string
  token: string
  lastUsedAt: number
}
