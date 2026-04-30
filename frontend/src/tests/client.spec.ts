import { describe, it, expect } from 'vitest'
import { camelizeResponse } from '../api/client'

describe('camelizeResponse', () => {
  it('converts standard snake_case keys', () => {
    const result = camelizeResponse<Record<string, unknown>>({ usage_count: 5, last_used_at: null })
    expect(result).toEqual({ usageCount: 5, lastUsedAt: null })
  })

  it('strips underscore before digit (kcal_per_100g → kcalPer100g)', () => {
    const raw = {
      kcal_per_100g: 36.0,
      protein_per_100g: 3.0,
      fat_per_100g: 0.1,
      carbs_per_100g: 5.0,
    }
    const result = camelizeResponse<Record<string, unknown>>(raw)
    expect(result.kcalPer100g).toBe(36.0)
    expect(result.proteinPer100g).toBe(3.0)
    expect(result.fatPer100g).toBe(0.1)
    expect(result.carbsPer100g).toBe(5.0)
    expect(result.kcalPer_100g).toBeUndefined()
  })

  it('handles nested arrays and objects', () => {
    const raw = { data: [{ kcal_per_100g: 100, nested_obj: { some_key: 1 } }] }
    const result = camelizeResponse<{ data: Record<string, unknown>[] }>(raw)
    expect(result.data[0].kcalPer100g).toBe(100)
    expect((result.data[0].nestedObj as Record<string, unknown>).someKey).toBe(1)
  })
})
