import { api } from './client'
import type { ActivityLevel, Gender, Profile } from '@/types/api'

interface ProfileResponse {
  data: Profile
}

export const profileApi = {
  get: () => api.get<ProfileResponse>('/profile'),

  upsert: (payload: {
    gender: Gender
    birthDate: string
    heightCm: number
    activityLevel: ActivityLevel
  }) => api.put<ProfileResponse>('/profile', payload),
}
