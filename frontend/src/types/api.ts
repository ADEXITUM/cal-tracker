export type ActivityLevel = 'sedentary' | 'light' | 'moderate' | 'active'
export type Gender = 'male' | 'female'
export type GoalType = 'cut' | 'maintenance' | 'bulk'
export type ModeCode = 'on_target' | 'over' | 'far_over' | 'under' | 'far_under'
export type MealSlot = 'breakfast' | 'lunch' | 'snack' | 'dinner' | 'other'

export type UserRole = 'user' | 'admin'

export interface User {
  uuid: string
  name: string
  email: string
  avatarColor: string
  timezone: string
  role: UserRole
  hasProfile: boolean
}

export interface ChatSender {
  uuid: string
  name: string
  avatarColor: string
}

export interface Profile {
  gender: Gender
  birthDate: string
  heightCm: number
  tdeeKcal: number | null
}

export interface Goal {
  uuid: string
  startDate: string
  endDate: string | null
  type: GoalType
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
  waistCm: number | null
  hipsCm: number | null
  chestCm: number | null
  bicepsCm: number | null
}

export interface Workout {
  uuid: string
  name: string
  durationMin: number | null
  kcalBurned: number | null
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
  isPiece: boolean
  pieceGrams: number | null
  pieceLabel: string | null
}

export interface Totals {
  kcal: number
  proteinG: number
  fatG: number
  carbsG: number
}

export interface DayGoal {
  uuid: string
  startDate: string
  endDate: string | null
  type: GoalType
  kcal: number
  proteinG: number
  fatG: number
  carbsG: number
  note: string | null
}

export interface DayMode {
  code: ModeCode
  label: string
  deltaKcal: number
}

export interface DayTdee {
  bmr: number
  baseKcal: number
  stepsKcal: number
  workoutsKcal: number
  total: number
}

export interface DayEntry {
  steps: number | null
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
  insights: Insight[]
}

export type InsightTone = 'neutral' | 'good' | 'warm' | 'warn' | 'alert'

export interface Insight {
  code: string
  tone: InsightTone
  title: string
  body: string
}

export interface SavedAccount {
  uuid: string
  email: string
  name: string
  avatarColor: string
  token: string
  lastUsedAt: number
}

export type ChatRole = 'user' | 'assistant'

export type ProposalStatus = 'pending' | 'approved' | 'rejected' | 'error'

export interface ChatIngredientBreakdown {
  name: string
  grams: number
  kcal: number
  proteinG: number
  fatG: number
  carbsG: number
}

export interface ChatProposal {
  targetUser: { uuid: string; name: string }
  label: string
  eatenGrams: number
  totalYieldGrams: number
  kcal: number
  proteinG: number
  fatG: number
  carbsG: number
  slot: MealSlot
  eatenAt: string
  ingredientsBreakdown: ChatIngredientBreakdown[]
  notes: string | null
}

export interface ChatTextBlock {
  type: 'text'
  text: string
}

export interface ChatToolUseBlock {
  type: 'tool_use'
  id: string
  name: string
  input: Record<string, unknown>
  result?: ChatProposal
  status?: ProposalStatus
  mealUuid?: string
  error?: string
}

export type ChatBlock = ChatTextBlock | ChatToolUseBlock

export interface ChatMessage {
  uuid: string
  role: ChatRole
  content: ChatBlock[]
  createdAt: string
  sender: ChatSender | null
}
