import { api } from './client'
import type { Goal, User } from '@/types/api'

interface AuthResponse {
  data: { user: User; token: string }
}

interface MeResponse {
  data: { user: User; currentGoal: Goal | null }
}

interface UpdateMeResponse {
  data: { user: User }
}

export const authApi = {
  register: (payload: { name: string; email: string; password: string; deviceName: string }) =>
    api.post<AuthResponse>('/auth/register', payload),

  login: (payload: { email: string; password: string; deviceName: string }) =>
    api.post<AuthResponse>('/auth/login', payload),

  logout: () => api.post<void>('/auth/logout'),

  me: () => api.get<MeResponse>('/auth/me'),

  updateMe: (payload: { name: string }) => api.put<UpdateMeResponse>('/auth/me', payload),
}
