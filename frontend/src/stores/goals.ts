import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { goalsApi } from '@/api/goals'
import type { Goal } from '@/types/api'

export const useGoalsStore = defineStore('goals', () => {
  const items = ref<Goal[]>([])
  const loading = ref(false)
  const loaded = ref(false)

  const sorted = computed(() =>
    [...items.value].sort((a, b) => b.startDate.localeCompare(a.startDate)),
  )

  const activeGoal = computed<Goal | null>(() => {
    const today = new Date().toISOString().slice(0, 10)
    return (
      items.value.find(g => g.startDate <= today && (g.endDate === null || g.endDate >= today)) ?? null
    )
  })

  async function fetchAll(force = false) {
    if (loaded.value && !force) return
    loading.value = true
    try {
      const res = await goalsApi.list()
      items.value = res.data
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Omit<Goal, 'uuid'>): Promise<Goal> {
    const res = await goalsApi.create(payload)
    await fetchAll(true)
    return res.data
  }

  async function update(uuid: string, payload: Omit<Goal, 'uuid'>): Promise<Goal> {
    const res = await goalsApi.update(uuid, payload)
    await fetchAll(true)
    return res.data
  }

  async function remove(uuid: string): Promise<void> {
    await goalsApi.delete(uuid)
    items.value = items.value.filter(g => g.uuid !== uuid)
  }

  async function endGoal(uuid: string): Promise<void> {
    const goal = items.value.find(g => g.uuid === uuid)
    if (!goal) return
    const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10)
    // Can't end before the goal started
    const endDate = yesterday < goal.startDate ? goal.startDate : yesterday
    await update(uuid, { ...goal, endDate })
  }

  return {
    items,
    sorted,
    loading,
    loaded,
    activeGoal,
    fetchAll,
    create,
    update,
    remove,
    endGoal,
  }
})
