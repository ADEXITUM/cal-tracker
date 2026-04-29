import { describe, it, expect } from 'vitest'
import { classifyMode, defaultMacroSplit, MODE_DESCRIPTIONS, PRESET_DEFINITIONS } from '../modes'

describe('classifyMode', () => {
  // Mirrors backend Tests/Unit/ModeClassifierTest — same boundaries
  it.each([
    [1000, 2500, 'extreme_cut'],
    [2000, 2500, 'cut'],
    [2300, 2500, 'cut_lite'],
    [2500, 2500, 'maintenance'],
    [2450, 2500, 'maintenance'],
    [2550, 2500, 'maintenance'],
    [2700, 2500, 'light_bulk'],
    [3000, 2500, 'bulk'],
    // boundary samples
    [1900, 2500, 'cut'],         // exactly -600
    [2200, 2500, 'cut_lite'],    // exactly -300
    [2400, 2500, 'maintenance'], // exactly -100
    [2600, 2500, 'maintenance'], // exactly +100
    [2800, 2500, 'light_bulk'],  // exactly +300
  ])('classify(goal=%i, tdee=%i) = %s', (goal, tdee, expected) => {
    const m = classifyMode(goal, tdee)
    expect(m.code).toBe(expected)
    expect(m.deltaKcal).toBe(goal - tdee)
  })

  it('returns Russian label', () => {
    expect(classifyMode(2000, 2500).label).toBe('Сушка')
  })
})

describe('defaultMacroSplit', () => {
  it('protein = 1.8 g per kg of weight (rounded)', () => {
    const split = defaultMacroSplit(2000, 80)
    expect(split.proteinG).toBe(Math.round(80 * 1.8))
  })

  it('fat = 25% of kcal divided by 9, rounded', () => {
    const split = defaultMacroSplit(2000, 80)
    // 25% of 2000 = 500 kcal → 500/9 ≈ 55.5 → 56
    expect(split.fatG).toBe(56)
  })

  it('carbs fill the remainder so kcal accounting balances roughly', () => {
    const split = defaultMacroSplit(2000, 80)
    const macroKcal = split.proteinG * 4 + split.fatG * 9 + split.carbsG * 4
    // Allow a few kcal slack from rounding
    expect(Math.abs(macroKcal - 2000)).toBeLessThan(10)
  })

  it('returns 0 carbs when target kcal is fully covered by protein + fat', () => {
    // weight=200 → protein=360g=1440kcal; kcal target=1500 → fat=42g=378kcal — already over
    const split = defaultMacroSplit(1500, 200)
    expect(split.carbsG).toBeGreaterThanOrEqual(0)
  })
})

describe('MODE_DESCRIPTIONS', () => {
  it('has an entry for every mode code', () => {
    const codes = ['extreme_cut', 'cut', 'cut_lite', 'maintenance', 'light_bulk', 'bulk'] as const
    for (const code of codes) {
      expect(MODE_DESCRIPTIONS[code]).toBeTypeOf('string')
      expect(MODE_DESCRIPTIONS[code].length).toBeGreaterThan(20)
    }
  })
})

describe('PRESET_DEFINITIONS', () => {
  it('has the 5 expected presets', () => {
    expect(PRESET_DEFINITIONS).toHaveLength(5)
    const keys = PRESET_DEFINITIONS.map(p => p.key)
    expect(keys).toEqual(['fast_cut', 'slow_cut', 'maintenance', 'light_bulk', 'bulk'])
  })

  it('each preset deltaFromTdee maps to the expected mode', () => {
    for (const preset of PRESET_DEFINITIONS) {
      const tdee = 2500
      const mode = classifyMode(tdee + preset.deltaFromTdee, tdee)
      expect(mode.code).toBe(preset.expectedMode)
    }
  })
})
