import { defineStore } from 'pinia'
import { ref } from 'vue'
import { daysApi } from '@/api/days'
import type { DayResource, Meal } from '@/types/api'

export const useDayStore = defineStore('day', () => {
  const currentDate = ref(todayString())
  const data = ref<DayResource | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  function todayString() {
    return new Date().toISOString().slice(0, 10)
  }

  async function setDate(date: string) {
    currentDate.value = date
    await fetch()
  }

  async function fetch() {
    loading.value = true
    error.value = null
    try {
      const res = await daysApi.get(currentDate.value)
      data.value = res.data
    } catch (e) {
      error.value = 'Не удалось загрузить данные'
    } finally {
      loading.value = false
    }
  }

  async function addMeal(payload: Record<string, unknown>) {
    const prev = data.value ? { ...data.value, meals: [...data.value.meals] } : null
    // Optimistic: append placeholder
    if (data.value) {
      const optimistic: Meal = {
        uuid: `tmp-${Date.now()}`,
        slot: payload.slot as Meal['slot'],
        eatenAt: payload.eatenAt as string ?? new Date().toISOString(),
        dishUuid: null,
        grams: payload.grams as number ?? null,
        name: payload.name as string ?? null,
        kcal: Number(payload.kcal ?? 0),
        proteinG: Number(payload.proteinG ?? 0),
        fatG: Number(payload.fatG ?? 0),
        carbsG: Number(payload.carbsG ?? 0),
      }
      data.value.meals.push(optimistic)
      updateTotals()
    }
    try {
      const res = await daysApi.addMeal(currentDate.value, payload)
      // Replace optimistic with real
      if (data.value) {
        const idx = data.value.meals.findIndex(m => m.uuid.startsWith('tmp-'))
        if (idx >= 0) data.value.meals.splice(idx, 1, res.data)
        updateTotals()
      }
    } catch {
      if (prev) data.value = prev
    }
  }

  async function deleteMeal(uuid: string) {
    const prev = data.value ? { ...data.value, meals: [...data.value.meals] } : null
    if (data.value) {
      data.value.meals = data.value.meals.filter(m => m.uuid !== uuid)
      updateTotals()
    }
    try {
      await daysApi.deleteMeal(uuid)
    } catch {
      if (prev) data.value = prev
    }
  }

  async function addMeasurement(payload: Record<string, unknown>) {
    try {
      const res = await daysApi.addMeasurement(currentDate.value, payload)
      if (data.value) data.value.measurements.push(res.data)
    } catch { /* surface to caller */ throw new Error('failed') }
  }

  async function deleteMeasurement(uuid: string) {
    if (data.value) data.value.measurements = data.value.measurements.filter(m => m.uuid !== uuid)
    try {
      await daysApi.deleteMeasurement(uuid)
    } catch {
      await fetch()
    }
  }

  async function addWorkout(payload: Record<string, unknown>) {
    try {
      const res = await daysApi.addWorkout(currentDate.value, payload)
      if (data.value) data.value.workouts.push(res.data)
    } catch { throw new Error('failed') }
  }

  async function deleteWorkout(uuid: string) {
    if (data.value) data.value.workouts = data.value.workouts.filter(w => w.uuid !== uuid)
    try {
      await daysApi.deleteWorkout(uuid)
    } catch {
      await fetch()
    }
  }

  function updateTotals() {
    if (!data.value) return
    const meals = data.value.meals
    data.value.totals = {
      kcal:      Math.round(meals.reduce((s, m) => s + m.kcal, 0) * 10) / 10,
      proteinG:  Math.round(meals.reduce((s, m) => s + m.proteinG, 0) * 10) / 10,
      fatG:      Math.round(meals.reduce((s, m) => s + m.fatG, 0) * 10) / 10,
      carbsG:    Math.round(meals.reduce((s, m) => s + m.carbsG, 0) * 10) / 10,
    }
  }

  function goTo(date: string) { setDate(date) }
  function goToToday() { setDate(todayString()) }
  function goToPrev() {
    const d = new Date(currentDate.value)
    d.setDate(d.getDate() - 1)
    setDate(d.toISOString().slice(0, 10))
  }
  function goToNext() {
    const d = new Date(currentDate.value)
    d.setDate(d.getDate() + 1)
    setDate(d.toISOString().slice(0, 10))
  }

  return {
    currentDate, data, loading, error,
    setDate, fetch, goTo, goToToday, goToPrev, goToNext,
    addMeal, deleteMeal,
    addMeasurement, deleteMeasurement,
    addWorkout, deleteWorkout,
  }
})
