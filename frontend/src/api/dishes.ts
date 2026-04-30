import { api } from './client'
import type { Dish } from '@/types/api'

interface DishResponse { data: Dish }
interface DishesResponse { data: Dish[] }

type DishPayload = Omit<Dish, 'uuid' | 'usageCount' | 'lastUsedAt'>

function toSnake(p: DishPayload) {
  return {
    name: p.name,
    kcal_per_100g: p.kcalPer100g,
    protein_per_100g: p.proteinPer100g,
    fat_per_100g: p.fatPer100g,
    carbs_per_100g: p.carbsPer100g,
  }
}

export const dishesApi = {
  list: (search?: string) =>
    api.get<DishesResponse>(`/dishes${search ? `?search=${encodeURIComponent(search)}` : ''}`),

  create: (payload: DishPayload) =>
    api.post<DishResponse>('/dishes', toSnake(payload)),

  update: (uuid: string, payload: DishPayload) =>
    api.put<DishResponse>(`/dishes/${uuid}`, toSnake(payload)),

  delete: (uuid: string) => api.delete<void>(`/dishes/${uuid}`),
}
