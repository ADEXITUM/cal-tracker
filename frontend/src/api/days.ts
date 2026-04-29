import { api } from './client'
import type { DayResource, Meal, Measurement, Workout } from '@/types/api'

interface DayResponse { data: DayResource }
interface MealResponse { data: Meal }
interface MeasurementResponse { data: Measurement }
interface WorkoutResponse { data: Workout }

export const daysApi = {
  get: (date: string) => api.get<DayResponse>(`/days/${date}`),

  update: (date: string, payload: Partial<{ mood: number; wellbeing: number; sleepHours: number; steps: number; notes: string }>) =>
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
