import { api } from './client'
import type { Dish } from '@/types/api'

interface DishResponse { data: Dish }
interface DishesResponse { data: Dish[] }

export type DishPayload = Omit<Dish, 'uuid' | 'usageCount' | 'lastUsedAt'>

export const dishesApi = {
  list: (search?: string) =>
    api.get<DishesResponse>(`/dishes${search ? `?search=${encodeURIComponent(search)}` : ''}`),

  create: (payload: DishPayload) =>
    api.post<DishResponse>('/dishes', payload),

  update: (uuid: string, payload: DishPayload) =>
    api.put<DishResponse>(`/dishes/${uuid}`, payload),

  delete: (uuid: string) => api.delete<void>(`/dishes/${uuid}`),
}
