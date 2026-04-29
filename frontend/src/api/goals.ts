import { api } from './client'
import type { Goal } from '@/types/api'

interface GoalResponse { data: Goal }
interface GoalsResponse { data: Goal[] }

export const goalsApi = {
  list: (params?: { from?: string; to?: string }) => {
    const qs = new URLSearchParams(params as Record<string, string>).toString()
    return api.get<GoalsResponse>(`/goals${qs ? `?${qs}` : ''}`)
  },

  create: (payload: Omit<Goal, 'uuid'>) => api.post<GoalResponse>('/goals', payload),

  update: (uuid: string, payload: Omit<Goal, 'uuid'>) =>
    api.put<GoalResponse>(`/goals/${uuid}`, payload),

  delete: (uuid: string) => api.delete<void>(`/goals/${uuid}`),
}
