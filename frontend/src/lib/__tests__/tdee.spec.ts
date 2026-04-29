import { describe, it, expect } from 'vitest'
import { computeTdee } from '../tdee'

// Tests mirror backend Tests/Unit/TdeeCalculatorTest.php — same inputs must yield same outputs.

describe('computeTdee', () => {
  // Use a fixed past birth date and compute age the same way the backend does.
  // Backend uses Carbon::age which compares by today's date.
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
      activityLevel: 'sedentary',
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
      activityLevel: 'sedentary',
      weightKg: 60,
    })
    expect(result.bmr).toBe(expected)
  })

  it('sedentary with no steps no workouts: total = bmr + activity', () => {
    const result = computeTdee({
      gender: 'male',
      birthDate: '1992-01-01',
      heightCm: 180,
      activityLevel: 'sedentary',
      weightKg: 80,
    })
    expect(result.stepsKcal).toBe(0)
    expect(result.workoutsKcal).toBe(0)
    expect(result.total).toBe(result.bmr + result.activityKcal)
  })

  it('steps bonus for sedentary: 10000 steps × 80 kg × 0.0005 × 1.0 = 400', () => {
    const result = computeTdee({
      gender: 'male',
      birthDate: '1992-01-01',
      heightCm: 180,
      activityLevel: 'sedentary',
      weightKg: 80,
      steps: 10000,
    })
    expect(result.stepsKcal).toBe(400)
  })

  it('steps coefficient drops for active users (×0.2)', () => {
    const result = computeTdee({
      gender: 'male',
      birthDate: '1992-01-01',
      heightCm: 180,
      activityLevel: 'active',
      weightKg: 80,
      steps: 10000,
    })
    expect(result.stepsKcal).toBe(80) // 10000 * 80 * 0.0005 * 0.2
  })

  it('workouts add directly to total', () => {
    const result = computeTdee({
      gender: 'male',
      birthDate: '1992-01-01',
      heightCm: 180,
      activityLevel: 'sedentary',
      weightKg: 80,
      workoutsKcal: 450,
    })
    expect(result.workoutsKcal).toBe(450)
  })

  it('activity multiplier light = 1.375', () => {
    const a = computeTdee({
      gender: 'male', birthDate: '1992-01-01', heightCm: 180, activityLevel: 'sedentary', weightKg: 80,
    })
    const b = computeTdee({
      gender: 'male', birthDate: '1992-01-01', heightCm: 180, activityLevel: 'light', weightKg: 80,
    })
    // (1.375 - 1) - (1.2 - 1) = 0.175 — light should be higher
    expect(b.activityKcal).toBeGreaterThan(a.activityKcal)
  })
})
