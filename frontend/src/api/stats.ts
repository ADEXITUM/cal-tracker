import { api } from './client'

export type StatsMetric =
  | 'weight'
  | 'body_fat_pct'
  | 'waist_cm'
  | 'hips_cm'
  | 'chest_cm'
  | 'biceps_cm'
  | 'kcal'
  | 'protein_g'
  | 'fat_g'
  | 'carbs_g'
  | 'steps'

export interface StatsSummary {
  weight: { start: number | null; end: number | null; deltaKg: number | null; trendKgPerWeek: number | null }
  bodyFatPct: { start: number | null; end: number | null; deltaPct: number | null }
  kcal: { avg: number | null; vsGoal: number | null; daysTracked: number; deficitAvg: number | null }
  activeDaysPct: number
  period: { from: string; to: string; days: number }
}

export interface StatsPoint {
  date: string
  value: number | null
}

export interface StatsSeries {
  metric: string
  points: StatsPoint[]
  rollingAvg7d: StatsPoint[]
}

interface SummaryResponse { data: StatsSummary }
interface SeriesResponse { data: StatsSeries }

export const statsApi = {
  summary: (from: string, to: string) =>
    api.get<SummaryResponse>(`/stats/summary?from=${from}&to=${to}`),

  series: (metric: StatsMetric, from: string, to: string) =>
    api.get<SeriesResponse>(`/stats/series?metric=${metric}&from=${from}&to=${to}`),
}
