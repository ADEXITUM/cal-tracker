import { api } from './client'
import type { Dish } from '@/types/api'

interface DishResponse { data: Dish }
interface DishesResponse { data: Dish[] }

export const dishesApi = {
  list: (search?: string) =>
    api.get<DishesResponse>(`/dishes${search ? `?search=${encodeURIComponent(search)}` : ''}`),

  create: (payload: Omit<Dish, 'uuid' | 'usageCount' | 'lastUsedAt'>) =>
    api.post<DishResponse>('/dishes', payload),

  update: (uuid: string, payload: Omit<Dish, 'uuid' | 'usageCount' | 'lastUsedAt'>) =>
    api.put<DishResponse>(`/dishes/${uuid}`, payload),

  delete: (uuid: string) => api.delete<void>(`/dishes/${uuid}`),
}
