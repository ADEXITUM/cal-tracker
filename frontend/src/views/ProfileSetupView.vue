<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { profileApi } from '@/api/profile'
import { goalsApi } from '@/api/goals'
import { ValidationError } from '@/api/client'
import AButton from '@/components/ui/AButton.vue'
import AInput from '@/components/ui/AInput.vue'
import type { ActivityLevel, Gender } from '@/types/api'

const router = useRouter()
const auth = useAuthStore()

const step = ref(1)
const loading = ref(false)
const errors = ref<Record<string, string>>({})

// Step 1
const gender = ref<Gender>('male')
const birthDate = ref('')

// Step 2
const heightCm = ref('')
const activityLevel = ref<ActivityLevel>('sedentary')

// Step 3
const kcal = ref('1700')
const proteinG = ref('150')
const fatG = ref('60')
const carbsG = ref('140')

const activityOptions: { value: ActivityLevel; label: string; hint: string }[] = [
  { value: 'sedentary', label: 'Сидячий', hint: 'Офис, мало движения' },
  { value: 'light', label: 'Лёгкий', hint: '1-3 тренировки в неделю' },
  { value: 'moderate', label: 'Умеренный', hint: '3-5 тренировок в неделю' },
  { value: 'active', label: 'Активный', hint: '6-7 тренировок или физический труд' },
]

async function nextStep() {
  errors.value = {}
  if (step.value < 3) { step.value++; return }
  await save()
}

async function save() {
  loading.value = true
  try {
    await profileApi.upsert({
      gender: gender.value,
      birthDate: birthDate.value,
      heightCm: parseInt(heightCm.value),
      activityLevel: activityLevel.value,
    })

    const today = new Date().toISOString().slice(0, 10)
    await goalsApi.create({
      startDate: today,
      endDate: null,
      kcal: parseInt(kcal.value),
      proteinG: parseInt(proteinG.value),
      fatG: parseInt(fatG.value),
      carbsG: parseInt(carbsG.value),
      note: null,
    })

    // Mark profile as complete in store so the router guard lets us through
    if (auth.currentUser) auth.currentUser.hasProfile = true
    await router.push({ name: 'day' })
  } catch (e) {
    if (e instanceof ValidationError) {
      Object.entries(e.errors).forEach(([k, v]) => { errors.value[k] = v[0] })
      if (errors.value.gender || errors.value.birthDate) step.value = 1
      else if (errors.value.heightCm || errors.value.activityLevel) step.value = 2
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-svh flex-col items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
      <div class="mb-6 flex gap-1">
        <div
          v-for="n in 3"
          :key="n"
          class="h-1 flex-1 rounded-full transition-colors"
          :style="{ background: n <= step ? 'var(--color-accent)' : 'var(--color-surface-3)' }"
        />
      </div>

      <h2 class="mb-6 text-xl font-semibold" style="color: var(--color-text)">
        {{ step === 1 ? 'Пол и дата рождения' : step === 2 ? 'Рост и активность' : 'Первая цель' }}
      </h2>

      <!-- Step 1 -->
      <div v-if="step === 1" class="flex flex-col gap-4">
        <div class="flex gap-2">
          <button
            v-for="opt in [{ value: 'male', label: 'Мужской' }, { value: 'female', label: 'Женский' }]"
            :key="opt.value"
            type="button"
            class="flex-1 rounded-[var(--radius-sm)] border py-2.5 text-base transition-colors"
            :style="{
              background: gender === opt.value ? 'var(--color-accent)' : 'var(--color-surface)',
              color: gender === opt.value ? 'white' : 'var(--color-text)',
              borderColor: gender === opt.value ? 'var(--color-accent)' : 'var(--color-border)',
            }"
            @click="gender = opt.value as Gender"
          >{{ opt.label }}</button>
        </div>
        <AInput
          v-model="birthDate"
          label="Дата рождения"
          type="date"
          :error="errors.birthDate"
        />
      </div>

      <!-- Step 2 -->
      <div v-else-if="step === 2" class="flex flex-col gap-4">
        <AInput
          v-model="heightCm"
          label="Рост (см)"
          type="number"
          placeholder="180"
          :error="errors.heightCm"
        />
        <div>
          <p class="mb-1 text-sm font-medium" style="color: var(--color-text)">Уровень активности</p>
          <div class="flex flex-col gap-2">
            <button
              v-for="opt in activityOptions"
              :key="opt.value"
              type="button"
              class="flex items-center justify-between rounded-[var(--radius-sm)] border px-3 py-2.5 text-left transition-colors"
              :style="{
                background: activityLevel === opt.value ? 'var(--color-accent-tint)' : 'var(--color-surface)',
                borderColor: activityLevel === opt.value ? 'var(--color-accent)' : 'var(--color-border)',
              }"
              @click="activityLevel = opt.value"
            >
              <span class="text-base font-medium" style="color: var(--color-text)">{{ opt.label }}</span>
              <span class="text-sm" style="color: var(--color-text-2)">{{ opt.hint }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 3 -->
      <div v-else class="flex flex-col gap-4">
        <p class="text-sm" style="color: var(--color-text-2)">
          Задайте начальную цель. Можно изменить в любое время.
        </p>
        <AInput v-model="kcal" label="Калории (ккал)" type="number" placeholder="1700" :error="errors.kcal" />
        <div class="grid grid-cols-3 gap-2">
          <AInput v-model="proteinG" label="Белки (г)" type="number" placeholder="150" :error="errors.proteinG" />
          <AInput v-model="fatG" label="Жиры (г)" type="number" placeholder="60" :error="errors.fatG" />
          <AInput v-model="carbsG" label="Углеводы (г)" type="number" placeholder="140" :error="errors.carbsG" />
        </div>
      </div>

      <div class="mt-8 flex gap-3">
        <AButton v-if="step > 1" variant="secondary" size="lg" class="flex-1" @click="step--">
          Назад
        </AButton>
        <AButton type="submit" size="lg" :loading="loading" class="flex-1" @click="nextStep">
          {{ step === 3 ? 'Готово' : 'Далее' }}
        </AButton>
      </div>
    </div>
  </div>
</template>
