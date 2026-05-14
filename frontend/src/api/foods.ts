import { api } from './client'
import type { FoodSearchHit, FoodDetail } from '@/types/api'

interface SearchResponse { data: FoodSearchHit[] }
interface DetailResponse { data: FoodDetail }

export const foodsApi = {
  search: (q: string, maxResults = 20) =>
    api.get<SearchResponse>(
      `/foods/search?q=${encodeURIComponent(q)}&max_results=${maxResults}`,
    ),

  get: (foodId: string) => api.get<DetailResponse>(`/foods/${foodId}`),
}
