import { defineStore } from 'pinia'
import { ref } from 'vue'
import { daysApi } from '@/api/days'
import { NetworkError } from '@/api/client'
import { enqueue as enqueueOffline } from '@/composables/useOfflineQueue'
import { readCachedDay, writeCachedDay } from '@/lib/dayCache'
import { useAuthStore } from '@/stores/auth'
import { useDishesStore } from '@/stores/dishes'
import { classifyMode } from '@/lib/modes'
import type { DayResource, Meal, Measurement, Workout } from '@/types/api'

function uuid(): string {
  return (typeof crypto !== 'undefined' && 'randomUUID' in crypto)
    ? crypto.randomUUID()
    : 'tmp-' + Math.random().toString(36).slice(2) + Date.now().toString(36)
}

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

  /**
   * Fetch the day from the server with read-through IDB cache.
   *
   * `skipCache` is true when called right after an optimistic mutation —
   * reading the cache there would clobber the freshly-pushed item with a
   * stale snapshot until the server response lands (visible flicker).
   */
  async function fetch({ skipCache = false }: { skipCache?: boolean } = {}) {
    error.value = null
    const auth = useAuthStore()
    const userUuid = auth.currentUser?.uuid ?? ''
    const dateAtStart = currentDate.value

    let cached: DayResource | null = null
    if (!skipCache) {
      cached = await readCachedDay(userUuid, dateAtStart)
      // The user (or date) might have switched while we were awaiting IDB.
      // Drop the result if the request is no longer current.
      if (auth.currentUser?.uuid !== userUuid || currentDate.value !== dateAtStart) return
      if (cached) {
        data.value = cached
        loading.value = false
      } else {
        loading.value = true
      }
    } else if (data.value === null) {
      loading.value = true
    }

    try {
      const res = await daysApi.get(dateAtStart)
      if (auth.currentUser?.uuid !== userUuid || currentDate.value !== dateAtStart) return
      data.value = res.data
      await writeCachedDay(userUuid, dateAtStart, res.data)
    } catch (e) {
      if (auth.currentUser?.uuid !== userUuid || currentDate.value !== dateAtStart) return
      if (cached || data.value) {
        // Silently keep current view — already rendered
      } else if (e instanceof NetworkError || !navigator.onLine) {
        error.value = 'Этот день ещё не открывался онлайн. Подключитесь к интернету.'
      } else {
        error.value = 'Не удалось загрузить данные'
      }
    } finally {
      if (auth.currentUser?.uuid === userUuid && currentDate.value === dateAtStart) {
        loading.value = false
      }
    }
  }

  /** Persist current optimistic state of the day to IDB so a reload
   *  before the server reply doesn't show a stale view. */
  async function persistOptimistic() {
    if (!data.value) return
    const auth = useAuthStore()
    const userUuid = auth.currentUser?.uuid ?? ''
    if (!userUuid) return
    await writeCachedDay(userUuid, currentDate.value, data.value)
  }

  /** Drop in-memory state. Call on logout or account switch. */
  function reset() {
    currentDate.value = todayString()
    data.value = null
    loading.value = false
    error.value = null
  }

  async function updateDayEntry(payload: Partial<{ steps: number }>) {
    // Optimistic local update so steps reflect immediately in TDEE-related UI.
    if (data.value) {
      data.value.dayEntry = { ...(data.value.dayEntry ?? {}), ...payload } as DayResource['dayEntry']
    }
    void persistOptimistic()
    try {
      await daysApi.update(currentDate.value, payload)
      // Refetch to recompute TDEE/mode (steps affect total burn).
      await fetch({ skipCache: true })
    } catch (e) {
      if (e instanceof NetworkError) {
        await enqueueOffline({
          id: uuid(),
          method: 'PUT',
          url: `/days/${currentDate.value}`,
          body: snakeify(payload),
        })
      } else {
        throw e
      }
    }
  }

  async function addMeal(payload: Record<string, unknown>) {
    const idempotencyKey = uuid()

    // For dish-based meals compute macros optimistically from the dish store
    let kcal = Number(payload.kcal ?? 0)
    let proteinG = Number(payload.proteinG ?? 0)
    let fatG = Number(payload.fatG ?? 0)
    let carbsG = Number(payload.carbsG ?? 0)
    let name = payload.name as string ?? null
    if (payload.dishUuid && payload.grams) {
      const dishStore = useDishesStore()
      const dish = dishStore.items.find(d => d.uuid === payload.dishUuid)
      if (dish) {
        const g = Number(payload.grams)
        kcal    = Math.round(dish.kcalPer100g    * g / 100)
        proteinG = Math.round(dish.proteinPer100g * g / 100)
        fatG    = Math.round(dish.fatPer100g     * g / 100)
        carbsG  = Math.round(dish.carbsPer100g   * g / 100)
        name    = dish.name
      }
    }

    const optimistic: Meal = {
      uuid: idempotencyKey,
      slot: payload.slot as Meal['slot'],
      eatenAt: payload.eatenAt as string ?? new Date().toISOString(),
      dishUuid: (payload.dishUuid as string) ?? null,
      grams: payload.grams as number ?? null,
      name,
      kcal,
      proteinG,
      fatG,
      carbsG,
    }
    if (data.value) {
      data.value.meals.push(optimistic)
      updateTotals()
      void persistOptimistic()
    }
    try {
      const res = await daysApi.addMeal(currentDate.value, payload, idempotencyKey)
      replaceMeal(idempotencyKey, res.data)
      // Refresh insights/mode from server in background (non-blocking).
      // skipCache: server response is the freshest data we have — we don't
      // want a stale IDB read to clobber it.
      void fetch({ skipCache: true })
    } catch (e) {
      if (e instanceof NetworkError) {
        await enqueueOffline({
          id: idempotencyKey,
          method: 'POST',
          url: `/days/${currentDate.value}/meals`,
          body: snakeify(payload),
        })
      } else {
        // permanent (validation/etc) — roll back
        rollbackMeal(idempotencyKey)
        throw e
      }
    }
  }

  function replaceMeal(tempUuid: string, real: Meal) {
    if (!data.value) return
    const idx = data.value.meals.findIndex(m => m.uuid === tempUuid)
    if (idx >= 0) data.value.meals.splice(idx, 1, real)
    updateTotals()
  }

  function rollbackMeal(tempUuid: string) {
    if (!data.value) return
    data.value.meals = data.value.meals.filter(m => m.uuid !== tempUuid)
    updateTotals()
  }

  async function deleteMeal(uuidStr: string) {
    const prev = data.value ? { ...data.value, meals: [...data.value.meals] } : null
    if (data.value) {
      data.value.meals = data.value.meals.filter(m => m.uuid !== uuidStr)
      updateTotals()
      void persistOptimistic()
    }
    try {
      await daysApi.deleteMeal(uuidStr)
      void fetch({ skipCache: true })
    } catch (e) {
      if (e instanceof NetworkError) {
        await enqueueOffline({
          id: uuid(),
          method: 'DELETE',
          url: `/meals/${uuidStr}`,
          body: null,
        })
      } else {
        if (prev) data.value = prev
      }
    }
  }

  async function addMeasurement(payload: Record<string, unknown>) {
    // Server upserts (one measurement per day), so the returned record may have
    // an existing uuid — we replace the day's measurements with [response].
    const idempotencyKey = uuid()
    try {
      const res = await daysApi.addMeasurement(currentDate.value, payload, idempotencyKey)
      if (data.value) {
        data.value.measurements = [res.data]
        void persistOptimistic()
      }
    } catch (e) {
      if (e instanceof NetworkError) {
        await enqueueOffline({
          id: idempotencyKey,
          method: 'POST',
          url: `/days/${currentDate.value}/measurements`,
          body: snakeify(payload),
        })
        // Optimistic local copy so the UI reflects the queued action
        if (data.value) {
          const num = (k: string) => payload[k] == null ? null : Number(payload[k])
          const optimistic: Measurement = {
            uuid: idempotencyKey,
            measuredAt: (payload.measuredAt as string) ?? new Date().toISOString(),
            weightKg: Number(payload.weightKg),
            bodyFatPct: num('bodyFatPct'),
            waistCm:    num('waistCm'),
            hipsCm:     num('hipsCm'),
            chestCm:    num('chestCm'),
            bicepsCm:   num('bicepsCm'),
          }
          data.value.measurements = [optimistic]
          void persistOptimistic()
        }
      } else {
        throw e
      }
    }
  }

  async function deleteMeasurement(uuidStr: string) {
    if (data.value) {
      data.value.measurements = data.value.measurements.filter(m => m.uuid !== uuidStr)
      void persistOptimistic()
    }
    try {
      await daysApi.deleteMeasurement(uuidStr)
    } catch (e) {
      if (e instanceof NetworkError) {
        await enqueueOffline({
          id: uuid(),
          method: 'DELETE',
          url: `/measurements/${uuidStr}`,
          body: null,
        })
      } else {
        await fetch({ skipCache: true })
      }
    }
  }

  async function addWorkout(payload: Record<string, unknown>) {
    const idempotencyKey = uuid()
    const optimistic: Workout = {
      uuid: idempotencyKey,
      name: payload.name as string,
      durationMin: payload.durationMin as number ?? null,
      kcalBurned: payload.kcalBurned as number ?? null,
    }
    if (data.value) {
      data.value.workouts.push(optimistic)
      updateWorkoutsTdee()
      void persistOptimistic()
    }

    try {
      const res = await daysApi.addWorkout(currentDate.value, payload, idempotencyKey)
      if (data.value) {
        const idx = data.value.workouts.findIndex(w => w.uuid === idempotencyKey)
        if (idx >= 0) data.value.workouts.splice(idx, 1, res.data)
        updateWorkoutsTdee()
      }
      void fetch({ skipCache: true })
    } catch (e) {
      if (e instanceof NetworkError) {
        await enqueueOffline({
          id: idempotencyKey,
          method: 'POST',
          url: `/days/${currentDate.value}/workouts`,
          body: snakeify(payload),
        })
      } else {
        if (data.value) {
          data.value.workouts = data.value.workouts.filter(w => w.uuid !== idempotencyKey)
          updateWorkoutsTdee()
        }
        throw e
      }
    }
  }

  async function deleteWorkout(uuidStr: string) {
    if (data.value) {
      data.value.workouts = data.value.workouts.filter(w => w.uuid !== uuidStr)
      updateWorkoutsTdee()
      void persistOptimistic()
    }
    try {
      await daysApi.deleteWorkout(uuidStr)
      void fetch({ skipCache: true })
    } catch (e) {
      if (e instanceof NetworkError) {
        await enqueueOffline({
          id: uuid(),
          method: 'DELETE',
          url: `/workouts/${uuidStr}`,
          body: null,
        })
      } else {
        await fetch()
      }
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
    updateMode()
  }

  function updateWorkoutsTdee() {
    if (!data.value?.tdee) return
    const workoutsKcal = data.value.workouts.reduce((s, w) => s + (w.kcalBurned ?? 0), 0)
    data.value.tdee = {
      ...data.value.tdee,
      workoutsKcal,
      total: data.value.tdee.baseKcal + data.value.tdee.stepsKcal + workoutsKcal,
    }
    updateMode()
  }

  function updateMode() {
    if (!data.value?.goal) return
    const m = classifyMode(data.value.goal.kcal, data.value.totals.kcal)
    data.value.mode = m
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
    setDate, fetch, reset, goTo, goToToday, goToPrev, goToNext,
    addMeal, deleteMeal,
    addMeasurement, deleteMeasurement, updateDayEntry,
    addWorkout, deleteWorkout,
  }
})

function snakeify(obj: unknown): unknown {
  if (Array.isArray(obj)) return obj.map(snakeify)
  if (obj !== null && typeof obj === 'object') {
    return Object.fromEntries(
      Object.entries(obj as Record<string, unknown>).map(([k, v]) => [
        k.replace(/[A-Z]/g, c => `_${c.toLowerCase()}`),
        snakeify(v),
      ]),
    )
  }
  return obj
}
