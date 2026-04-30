import { describe, it, expect } from 'vitest'
import { computeTdee, BASE_MULTIPLIER } from '../tdee'

// Tests mirror backend Tests/Unit/TdeeCalculatorTest.php — same inputs must yield same outputs.

describe('computeTdee', () => {
  const ageOf = (birth: string) => {
    const b = new Date(birth + 'T12:00:00')
    const now = new Date()
    let age = now.getFullYear() - b.getFullYear()
    const m = now.getMonth() - b.getMonth()
    if (m < 0 || (m === 0 && now.getDate() < b.getDate())) age--
    return age
  }

  it('male BMR matches Mifflin-St Jeor', () => {
    const age = ageOf('1992-01-01')
    const expected = Math.round(10 * 80 + 6.25 * 180 - 5 * age + 5)
    const result = computeTdee({
      gender: 'male',
      birthDate: '1992-01-01',
      heightCm: 180,
      weightKg: 80,
    })
    expect(result.bmr).toBe(expected)
  })

  it('female BMR uses -161 constant', () => {
    const age = ageOf('1992-01-01')
    const expected = Math.round(10 * 60 + 6.25 * 165 - 5 * age - 161)
    const result = computeTdee({
      gender: 'female',
      birthDate: '1992-01-01',
      heightCm: 165,
      weightKg: 60,
    })
    expect(result.bmr).toBe(expected)
  })

  it('baseKcal = bmr × BASE_MULTIPLIER', () => {
    const result = computeTdee({
      gender: 'male', birthDate: '1992-01-01', heightCm: 180, weightKg: 80,
    })
    expect(result.baseKcal).toBe(Math.round(result.bmr * BASE_MULTIPLIER))
  })

  it('no steps no workouts: total = baseKcal', () => {
    const result = computeTdee({
      gender: 'male', birthDate: '1992-01-01', heightCm: 180, weightKg: 80,
    })
    expect(result.stepsKcal).toBe(0)
    expect(result.workoutsKcal).toBe(0)
    expect(result.total).toBe(result.baseKcal)
  })

  it('steps: 10000 steps × 80 kg × 0.0005 = 400', () => {
    const result = computeTdee({
      gender: 'male', birthDate: '1992-01-01', heightCm: 180, weightKg: 80, steps: 10000,
    })
    expect(result.stepsKcal).toBe(400)
  })

  it('workouts add directly to total', () => {
    const result = computeTdee({
      gender: 'male', birthDate: '1992-01-01', heightCm: 180, weightKg: 80, workoutsKcal: 450,
    })
    expect(result.workoutsKcal).toBe(450)
  })

  it('total sums components', () => {
    const result = computeTdee({
      gender: 'male', birthDate: '1992-01-01', heightCm: 180, weightKg: 80,
      steps: 8000, workoutsKcal: 200,
    })
    expect(result.total).toBe(result.baseKcal + result.stepsKcal + result.workoutsKcal)
  })
})
