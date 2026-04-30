import { api } from './client'
import type { DayResource, Meal, Measurement, ModeCode, Workout } from '@/types/api'

interface DayResponse { data: DayResource }
interface MealResponse { data: Meal }
interface MeasurementResponse { data: Measurement }
interface WorkoutResponse { data: Workout }

export interface DaySummary {
  date: string
  totals: { kcal: number; proteinG: number; fatG: number; carbsG: number }
  weightKg: number | null
  modeCode: ModeCode | null
  deltaFromGoal: number | null
}

interface DaySummariesResponse { data: DaySummary[] }

export const daysApi = {
  get: (date: string) => api.get<DayResponse>(`/days/${date}`),

  list: (from: string, to: string) =>
    api.get<DaySummariesResponse>(`/days?from=${from}&to=${to}`),

  update: (date: string, payload: Partial<{ steps: number }>) =>
    api.put<DayResponse>(`/days/${date}`, payload),

  addMeal: (date: string, payload: Record<string, unknown>, idempotencyKey?: string) =>
    api.post<MealResponse>(`/days/${date}/meals`, payload, idempotencyKey ? { idempotencyKey } : undefined),

  updateMeal: (uuid: string, payload: Record<string, unknown>) =>
    api.put<MealResponse>(`/meals/${uuid}`, payload),

  deleteMeal: (uuid: string) => api.delete<void>(`/meals/${uuid}`),

  addMeasurement: (date: string, payload: Record<string, unknown>, idempotencyKey?: string) =>
    api.post<MeasurementResponse>(`/days/${date}/measurements`, payload, idempotencyKey ? { idempotencyKey } : undefined),

  deleteMeasurement: (uuid: string) => api.delete<void>(`/measurements/${uuid}`),

  addWorkout: (date: string, payload: Record<string, unknown>, idempotencyKey?: string) =>
    api.post<WorkoutResponse>(`/days/${date}/workouts`, payload, idempotencyKey ? { idempotencyKey } : undefined),

  deleteWorkout: (uuid: string) => api.delete<void>(`/workouts/${uuid}`),
}
