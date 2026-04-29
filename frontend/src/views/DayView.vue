<script setup lang="ts">
import { onMounted, computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDayStore } from '@/stores/day'
import { useAuthStore } from '@/stores/auth'
import { useSwipe } from '@/composables/useSwipe'
import KcalRing from '@/components/charts/KcalRing.vue'
import AModeBadge from '@/components/ui/AModeBadge.vue'
import ACard from '@/components/ui/ACard.vue'
import AButton from '@/components/ui/AButton.vue'
import DayFab from '@/components/day/DayFab.vue'
import AddMealSheet from '@/components/add/AddMealSheet.vue'
import AddMeasurementSheet from '@/components/add/AddMeasurementSheet.vue'
import AddWorkoutSheet from '@/components/add/AddWorkoutSheet.vue'

const route = useRoute()
const router = useRouter()
const day = useDayStore()
const auth = useAuthStore()

const showAddMeal = ref(false)
const showAddMeasurement = ref(false)
const showAddWorkout = ref(false)

const dateParam = computed(() => (route.params.date as string) || new Date().toISOString().slice(0, 10))

const displayDate = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  const d = new Date(dateParam.value + 'T12:00:00')
  if (dateParam.value === today) return 'Сегодня'
  if (dateParam.value === new Date(Date.now() - 86400000).toISOString().slice(0, 10)) return 'Вчера'
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' })
})

onMounted(async () => {
  await day.setDate(dateParam.value)
})

useSwipe({
  onLeft: () => {
    day.goToNext()
    router.replace({ name: 'day', params: { date: day.currentDate } })
  },
  onRight: () => {
    day.goToPrev()
    router.replace({ name: 'day', params: { date: day.currentDate } })
  },
})

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}

function openAdd(type: 'meal' | 'measurement' | 'workout') {
  if (type === 'meal') showAddMeal.value = true
  else if (type === 'measurement') showAddMeasurement.value = true
  else showAddWorkout.value = true
}
</script>

<template>
  <div class="flex flex-col min-h-svh" style="background: var(--color-bg)">
    <!-- Header -->
    <header class="sticky top-0 z-10 flex items-center justify-between px-4 py-3"
      style="background: var(--color-bg); border-bottom: 1px solid var(--color-border)">
      <button class="p-1 -ml-1" style="color: var(--color-text-2)" @click="day.goToPrev(); router.replace({ name: 'day', params: { date: day.currentDate } })">
        ←
      </button>
      <h1 class="text-base font-semibold" style="color: var(--color-text)">{{ displayDate }}</h1>
      <button class="p-1 -mr-1" style="color: var(--color-text-2)" @click="day.goToNext(); router.replace({ name: 'day', params: { date: day.currentDate } })">
        →
      </button>
    </header>

    <!-- Loading -->
    <div v-if="day.loading" class="flex flex-col gap-3 p-4">
      <div class="h-56 rounded-[var(--radius-md)] animate-pulse" style="background: var(--color-surface-2)" />
      <div class="h-20 rounded-[var(--radius-md)] animate-pulse" style="background: var(--color-surface-2)" />
      <div class="h-32 rounded-[var(--radius-md)] animate-pulse" style="background: var(--color-surface-2)" />
    </div>

    <!-- Content -->
    <div v-else-if="day.data" class="flex flex-col gap-3 p-4 pb-24">

      <!-- Kcal ring + mode -->
      <ACard>
        <div class="flex flex-col items-center py-6 gap-3">
          <KcalRing
            :current="day.data.totals.kcal"
            :goal="day.data.goal?.kcal ?? 0"
          />
          <AModeBadge
            v-if="day.data.mode"
            :code="day.data.mode.code"
            :label="day.data.mode.label"
            :delta-kcal="day.data.mode.deltaKcal"
          />
        </div>
      </ACard>

      <!-- Macros -->
      <div class="grid grid-cols-3 gap-2">
        <ACard v-for="macro in [
          { label: 'Белки', key: 'proteinG', goal: day.data.goal?.proteinG },
          { label: 'Жиры', key: 'fatG', goal: day.data.goal?.fatG },
          { label: 'Углеводы', key: 'carbsG', goal: day.data.goal?.carbsG },
        ]" :key="macro.key">
          <div class="px-3 py-4 text-center">
            <p class="text-xs mb-1" style="color: var(--color-text-3)">{{ macro.label }}</p>
            <p class="font-mono text-xl font-light" style="color: var(--color-text)">
              {{ (day.data.totals as any)[macro.key] }}
            </p>
            <p v-if="macro.goal" class="text-xs mt-0.5" style="color: var(--color-text-3)">/ {{ macro.goal }} г</p>
          </div>
        </ACard>
      </div>

      <!-- Meals -->
      <ACard>
        <div class="p-4">
          <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold" style="color: var(--color-text)">Приёмы пищи</p>
            <button class="text-sm" style="color: var(--color-accent)" @click="showAddMeal = true">+ Добавить</button>
          </div>
          <div v-if="day.data.meals.length === 0" class="text-sm py-2" style="color: var(--color-text-3)">
            Нет записей
          </div>
          <div v-else class="flex flex-col divide-y" style="border-color: var(--color-border)">
            <div v-for="meal in day.data.meals" :key="meal.uuid" class="flex items-center justify-between py-2.5">
              <div>
                <p class="text-sm font-medium" style="color: var(--color-text)">{{ meal.name }}</p>
                <p class="text-xs" style="color: var(--color-text-3)">
                  {{ meal.slot }} · {{ meal.grams ? `${meal.grams} г · ` : '' }}{{ meal.kcal }} ккал
                </p>
              </div>
              <button class="p-1 text-xs" style="color: var(--color-text-3)" @click="day.deleteMeal(meal.uuid)">✕</button>
            </div>
          </div>
        </div>
      </ACard>

      <!-- Measurements -->
      <ACard>
        <div class="p-4">
          <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold" style="color: var(--color-text)">Замеры</p>
            <button class="text-sm" style="color: var(--color-accent)" @click="showAddMeasurement = true">+ Добавить</button>
          </div>
          <div v-if="day.data.measurements.length === 0" class="text-sm py-2" style="color: var(--color-text-3)">Нет замеров</div>
          <div v-else class="flex flex-col gap-2">
            <div v-for="m in day.data.measurements" :key="m.uuid" class="flex items-center justify-between">
              <div>
                <span class="font-mono text-lg font-light" style="color: var(--color-text)">{{ m.weightKg }} кг</span>
                <span v-if="m.bodyFatPct" class="text-xs ml-2" style="color: var(--color-text-3)">{{ m.bodyFatPct }}% жира</span>
              </div>
              <button class="p-1 text-xs" style="color: var(--color-text-3)" @click="day.deleteMeasurement(m.uuid)">✕</button>
            </div>
          </div>
        </div>
      </ACard>

      <!-- Workouts -->
      <ACard>
        <div class="p-4">
          <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold" style="color: var(--color-text)">Тренировки</p>
            <button class="text-sm" style="color: var(--color-accent)" @click="showAddWorkout = true">+ Добавить</button>
          </div>
          <div v-if="day.data.workouts.length === 0" class="text-sm py-2" style="color: var(--color-text-3)">Нет тренировок</div>
          <div v-else class="flex flex-col gap-2">
            <div v-for="w in day.data.workouts" :key="w.uuid" class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium" style="color: var(--color-text)">{{ w.name }}</p>
                <p class="text-xs" style="color: var(--color-text-3)">
                  {{ w.durationMin ? `${w.durationMin} мин` : '' }}{{ w.kcalBurned ? ` · ${w.kcalBurned} ккал` : '' }}
                </p>
              </div>
              <button class="p-1 text-xs" style="color: var(--color-text-3)" @click="day.deleteWorkout(w.uuid)">✕</button>
            </div>
          </div>
        </div>
      </ACard>

      <!-- Logout -->
      <div class="pt-2 flex justify-center">
        <AButton variant="ghost" size="sm" @click="logout">Выйти</AButton>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="day.error" class="flex flex-col items-center justify-center flex-1 gap-3 p-8">
      <p style="color: var(--color-text-2)">{{ day.error }}</p>
      <AButton @click="day.fetch()">Повторить</AButton>
    </div>

    <!-- FAB -->
    <DayFab @add="openAdd" />

    <!-- Sheets -->
    <AddMealSheet v-model="showAddMeal" />
    <AddMeasurementSheet v-model="showAddMeasurement" />
    <AddWorkoutSheet v-model="showAddWorkout" />
  </div>
</template>
