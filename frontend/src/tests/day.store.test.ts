import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('idb-keyval', () => ({
  get: vi.fn().mockResolvedValue(null),
  set: vi.fn().mockResolvedValue(undefined),
  del: vi.fn().mockResolvedValue(undefined),
  keys: vi.fn().mockResolvedValue([]),
}))

vi.mock('@/api/days', () => ({
  daysApi: {
    get: vi.fn(),
    addMeal: vi.fn(),
    deleteMeal: vi.fn(),
    update: vi.fn(),
    addMeasurement: vi.fn(),
    deleteMeasurement: vi.fn(),
    addWorkout: vi.fn(),
    deleteWorkout: vi.fn(),
  },
}))

vi.mock('@/api/auth', () => ({
  authApi: {
    me: vi.fn(),
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    updateMe: vi.fn(),
  },
}))

vi.mock('@/api/client', () => ({
  configureClient: vi.fn(),
  NetworkError: class NetworkError extends Error {},
  ValidationError: class ValidationError extends Error {
    constructor(public errors: Record<string, string[]>) { super() }
  },
  ApiError: class ApiError extends Error {
    constructor(message: string, public status: number) { super(message) }
  },
}))

import { daysApi } from '@/api/days'
import { useDayStore } from '@/stores/day'
import { useAuthStore } from '@/stores/auth'
import type { DayResource, Meal } from '@/types/api'

function emptyDay(date: string, meals: Meal[] = []): DayResource {
  return {
    date,
    dayEntry: { steps: null, weightKg: null, sleepHours: null, mood: null, notes: null },
    meals,
    measurements: [],
    workouts: [],
    totals: { kcal: 0, proteinG: 0, fatG: 0, carbsG: 0 },
    goal: null,
    tdee: null,
    mode: null,
    insights: [],
  } as unknown as DayResource
}

function makeMeal(uuid: string, kcal = 100): Meal {
  return {
    uuid, slot: 'lunch', eatenAt: '2026-05-03T12:00:00Z', dishUuid: null,
    grams: null, name: 'm', kcal, proteinG: 0, fatG: 0, carbsG: 0,
  } as Meal
}

describe('day store — stale-fetch protection', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    const auth = useAuthStore()
    auth.currentUser = {
      uuid: 'u-1', name: 'A', email: 'a@x', avatarColor: '#FF', timezone: 'UTC', hasProfile: true,
    } as never
  })

  it('discards an in-flight fetch result if the user logs out / store resets', async () => {
    const day = useDayStore()

    let resolveGet: (v: { data: DayResource }) => void = () => {}
    vi.mocked(daysApi.get).mockReturnValueOnce(
      new Promise((r) => { resolveGet = r }),
    )

    const inFlight = day.fetch({ skipCache: true })

    // Account switch / logout happens mid-flight. reset() bumps the version
    // so the response below must NOT be written into data.
    day.reset()

    resolveGet({ data: emptyDay('2026-05-03', [makeMeal('m-server', 999)]) })
    await inFlight

    expect(day.data).toBeNull()
  })

  it('discards an in-flight fetch if the user navigates to a different date first', async () => {
    const day = useDayStore()

    let resolveGet: (v: { data: DayResource }) => void = () => {}
    vi.mocked(daysApi.get).mockImplementation((date: string) => {
      if (date === '2026-05-03') return new Promise((r) => { resolveGet = r }) as never
      return Promise.resolve({ data: emptyDay(date, [makeMeal('other')]) }) as never
    })

    day.currentDate = '2026-05-03'
    const first = day.fetch({ skipCache: true })

    // Navigate to another day before the first response lands.
    await day.setDate('2026-05-04')

    // First (now stale) GET resolves last.
    resolveGet({ data: emptyDay('2026-05-03', [makeMeal('stale')]) })
    await first

    // We should be looking at 05-04's data, not the stale 05-03 response.
    expect(day.currentDate).toBe('2026-05-04')
    expect(day.data?.date).toBe('2026-05-04')
  })
})
