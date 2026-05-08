import { defineStore } from 'pinia'
import { ref } from 'vue'
import { dishesApi } from '@/api/dishes'
import type { Dish } from '@/types/api'

export const useDishesStore = defineStore('dishes', () => {
  const items = ref<Dish[]>([])
  const loading = ref(false)
  const cachedAt = ref(0)
  const TTL = 5 * 60 * 1000

  async function fetchAll(force = false) {
    if (!force && items.value.length && Date.now() - cachedAt.value < TTL) return
    loading.value = true
    try {
      const res = await dishesApi.list()
      items.value = res.data
      cachedAt.value = Date.now()
    } finally {
      loading.value = false
    }
  }

  function reset() {
    items.value = []
    cachedAt.value = 0
    loading.value = false
  }

  function search(query: string): Dish[] {
    if (!query) return items.value
    const q = query.toLowerCase()
    return items.value.filter(d => d.name.toLowerCase().includes(q))
  }

  async function create(payload: Omit<Dish, 'uuid' | 'usageCount' | 'lastUsedAt'>) {
    const res = await dishesApi.create(payload)
    items.value.unshift(res.data)
    return res.data
  }

  async function update(uuid: string, payload: Omit<Dish, 'uuid' | 'usageCount' | 'lastUsedAt'>) {
    const res = await dishesApi.update(uuid, payload)
    const idx = items.value.findIndex(d => d.uuid === uuid)
    if (idx >= 0) items.value.splice(idx, 1, res.data)
    return res.data
  }

  async function remove(uuid: string) {
    await dishesApi.delete(uuid)
    items.value = items.value.filter(d => d.uuid !== uuid)
  }

  return { items, loading, fetchAll, reset, search, create, update, remove }
})
