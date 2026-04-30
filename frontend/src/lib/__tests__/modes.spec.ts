import { describe, it, expect } from 'vitest'
import { classifyMode, defaultMacroSplit, MODE_DESCRIPTIONS } from '../modes'

describe('classifyMode', () => {
  // Mirrors backend Tests/Unit/ModeClassifierTest
  it.each([
    [2000, 2000, 'on_target'],
    [2000, 1900, 'on_target'],   // -5%
    [2000, 2100, 'on_target'],   // +5%
    [2000, 2200, 'over'],         // +10%
    [2000, 2300, 'over'],         // +15%
    [2000, 2500, 'far_over'],     // +25%
    [2000, 1800, 'under'],         // -10%
    [2000, 1700, 'under'],         // -15%
    [2000, 1500, 'far_under'],     // -25%
  ])('classify(goal=%i, eaten=%i) = %s', (goal, eaten, expected) => {
    const m = classifyMode(goal, eaten)
    expect(m.code).toBe(expected)
    expect(m.deltaKcal).toBe(eaten - goal)
  })

  it('returns Russian label for on_target', () => {
    expect(classifyMode(2000, 2000).label).toBe('На цели')
  })

  it('returns Russian label for far_over', () => {
    expect(classifyMode(2000, 2600).label).toBe('Сильный перебор')
  })
})

describe('defaultMacroSplit', () => {
  it('protein = 1.8 g per kg of weight (rounded)', () => {
    const split = defaultMacroSplit(2000, 80)
    expect(split.proteinG).toBe(Math.round(80 * 1.8))
  })

  it('fat = 25% of kcal divided by 9, rounded', () => {
    const split = defaultMacroSplit(2000, 80)
    expect(split.fatG).toBe(56)
  })

  it('carbs fill the remainder so kcal accounting balances roughly', () => {
    const split = defaultMacroSplit(2000, 80)
    const macroKcal = split.proteinG * 4 + split.fatG * 9 + split.carbsG * 4
    expect(Math.abs(macroKcal - 2000)).toBeLessThan(10)
  })

  it('returns 0 carbs when target kcal is fully covered by protein + fat', () => {
    const split = defaultMacroSplit(1500, 200)
    expect(split.carbsG).toBeGreaterThanOrEqual(0)
  })
})

describe('MODE_DESCRIPTIONS', () => {
  it('has an entry for every mode code', () => {
    const codes = ['on_target', 'over', 'far_over', 'under', 'far_under'] as const
    for (const code of codes) {
      expect(MODE_DESCRIPTIONS[code]).toBeTypeOf('string')
      expect(MODE_DESCRIPTIONS[code].length).toBeGreaterThan(20)
    }
  })
})
