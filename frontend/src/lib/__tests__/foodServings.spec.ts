import { describe, it, expect } from 'vitest'
import { servingToMealPayload, formatServingLine } from '../foodServings'
import type { FoodServing } from '@/types/api'

const apple100g: FoodServing = {
  servingId: '1',
  description: '100 g',
  metricAmount: 100,
  metricUnit: 'g',
  grams: 100,
  kcal: 52,
  proteinG: 0.3,
  fatG: 0.2,
  carbsG: 14,
}

const cup: FoodServing = {
  servingId: '2',
  description: '1 cup',
  metricAmount: 240,
  metricUnit: 'g',
  grams: 240,
  kcal: 130,
  proteinG: 0.6,
  fatG: 0.4,
  carbsG: 34,
}

const noGrams: FoodServing = {
  servingId: '3',
  description: '1 medium',
  metricAmount: null,
  metricUnit: null,
  grams: null,
  kcal: 95,
  proteinG: 0.5,
  fatG: 0.3,
  carbsG: 25,
}

describe('servingToMealPayload', () => {
  it('returns serving values rounded for count=1', () => {
    const p = servingToMealPayload('Apple', cup, 1)
    expect(p.kcal).toBe(130)
    expect(p.proteinG).toBe(0.6)
    expect(p.fatG).toBe(0.4)
    expect(p.carbsG).toBe(34)
  })

  it('multiplies by count', () => {
    const p = servingToMealPayload('Apple', apple100g, 2.5)
    expect(p.kcal).toBe(130)               // 52 * 2.5
    expect(p.proteinG).toBe(0.8)           // 0.3 * 2.5 = 0.75 → 0.8 (округление до десятых)
    expect(p.carbsG).toBe(35)              // 14 * 2.5 = 35
  })

  it('falls back to count=1 for non-positive count', () => {
    expect(servingToMealPayload('X', cup, 0).kcal).toBe(130)
    expect(servingToMealPayload('X', cup, -3).kcal).toBe(130)
    expect(servingToMealPayload('X', cup, NaN).kcal).toBe(130)
  })

  it('preserves provided name', () => {
    expect(servingToMealPayload('Banana', cup, 1).name).toBe('Banana')
  })
})

describe('formatServingLine', () => {
  it('shows grams when known', () => {
    expect(formatServingLine(cup)).toBe('1 cup (240 г) · 130 ккал')
  })

  it('omits grams when unknown', () => {
    expect(formatServingLine(noGrams)).toBe('1 medium · 95 ккал')
  })

  it('rounds fractional grams to 1 decimal', () => {
    const s: FoodServing = { ...cup, grams: 42.36, description: 'half cup' }
    expect(formatServingLine(s)).toBe('half cup (42.4 г) · 130 ккал')
  })
})
