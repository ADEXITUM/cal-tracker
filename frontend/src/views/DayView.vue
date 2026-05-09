<script setup lang="ts">
import { onMounted, computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDayStore } from '@/stores/day'
import { daysApi } from '@/api/days'
import { useSwipe } from '@/composables/useSwipe'
import { MS_PER_DAY, previousDayIso } from '@/lib/time'
import type { DayResource } from '@/types/api'
import KcalRing from '@/components/charts/KcalRing.vue'
import AModeBadge from '@/components/ui/AModeBadge.vue'
import ACard from '@/components/ui/ACard.vue'
import AButton from '@/components/ui/AButton.vue'
import DayFab from '@/components/day/DayFab.vue'
import DayBalanceCard from '@/components/day/DayBalanceCard.vue'
import AddMealSheet from '@/components/add/AddMealSheet.vue'
import type { Meal } from '@/types/api'
import AddMeasurementSheet from '@/components/add/AddMeasurementSheet.vue'
import AddStepsSheet from '@/components/add/AddStepsSheet.vue'
import AddWorkoutSheet from '@/components/add/AddWorkoutSheet.vue'
import ModeExplainerModal from '@/components/day/ModeExplainerModal.vue'
import DayInsights from '@/components/day/DayInsights.vue'
import AConfirm from '@/components/ui/AConfirm.vue'
import { GOAL_TYPE_LABEL } from '@/lib/modes'

const route = useRoute()
const router = useRouter()
const day = useDayStore()

const showAddMeal = ref(false)
const showAddMeasurement = ref(false)
const showAddSteps = ref(false)
const showAddWorkout = ref(false)
const showModeExplainer = ref(false)

const editingMeal = ref<Meal | null>(null)
const showEditMeal = ref(false)
const mealToDelete = ref<{ uuid: string; name: string } | null>(null)
const workoutToDelete = ref<{ uuid: string; name: string } | null>(null)

function startEditMeal(m: Meal) {
  editingMeal.value = m
  showEditMeal.value = true
}

watch(showEditMeal, (v) => { if (!v) editingMeal.value = null })

// AConfirm emits update:modelValue=false BEFORE confirm. Our @update:modelValue
// nulls *toDelete, so by the time confirm fires the captured ref is gone and
// nothing gets deleted. Read the value at click time, not in the handler.
function confirmDeleteMeal() {
  const m = mealToDelete.value
  if (m) void day.deleteMeal(m.uuid)
  mealToDelete.value = null
}

function confirmDeleteWorkout() {
  const w = workoutToDelete.value
  if (w) void day.deleteWorkout(w.uuid)
  workoutToDelete.value = null
}

const activeTab = ref<'goal' | 'balance'>('goal')

const prevDayData = ref<DayResource | null>(null)
const prevDayDate = ref<string | null>(null)

/** Look back this far when finding the most-recent day with logged data. */
const PREV_DAY_LOOKBACK_DAYS = 60

const prevDate = previousDayIso

async function fetchPrevDay(date: string) {
  try {
    // Find the most recent prior day that actually has data
    const from = new Date(new Date(date + 'T12:00:00').getTime() - PREV_DAY_LOOKBACK_DAYS * MS_PER_DAY).toISOString().slice(0, 10)
    const listRes = await daysApi.list(from, prevDate(date))
    const withData = listRes.data
      .filter(d => d.date < date && (d.totals.kcal > 0 || d.weightKg !== null || d.modeCode !== null))
      .sort((a, b) => b.date.localeCompare(a.date))
    if (withData.length === 0) {
      prevDayData.value = null
      return
    }
    prevDayDate.value = withData[0].date
    const res = await daysApi.get(withData[0].date)
    prevDayData.value = res.data
  } catch {
    prevDayData.value = null
    prevDayDate.value = null
  }
}

function fmtDelta(val: number): string {
  return (val > 0 ? '+' : '') + val
}

function deltaIcon(val: number): string {
  if (val > 0) return '▲'
  if (val < 0) return '▼'
  return '='
}

const dateParam = computed(() => (route.params.date as string) || new Date().toISOString().slice(0, 10))

const displayDate = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  const d = new Date(dateParam.value + 'T12:00:00')
  if (dateParam.value === today) return 'Сегодня'
  if (dateParam.value === new Date(Date.now() - MS_PER_DAY).toISOString().slice(0, 10)) return 'Вчера'
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' })
})

onMounted(async () => {
  await day.setDate(dateParam.value)
  void fetchPrevDay(dateParam.value)
})

watch(dateParam, (date) => {
  prevDayData.value = null
  prevDayDate.value = null
  // Component is no longer keyed by path, so we drive store on date change.
  void day.setDate(date)
  void fetchPrevDay(date)
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

function openAdd(type: 'meal' | 'measurement' | 'steps' | 'workout') {
  if (type === 'meal') showAddMeal.value = true
  else if (type === 'measurement') showAddMeasurement.value = true
  else if (type === 'steps') showAddSteps.value = true
  else showAddWorkout.value = true
}

function formatShortDate(iso: string): string {
  return new Date(iso + 'T12:00:00').toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
}

function daysBetween(fromIso: string, toIso: string): number {
  const a = new Date(fromIso + 'T12:00:00').getTime()
  const b = new Date(toIso + 'T12:00:00').getTime()
  return Math.round((b - a) / MS_PER_DAY)
}

const macroCards = computed(() => {
  const totals = day.data?.totals
  const goal = day.data?.goal
  const make = (label: string, current: number, goalVal?: number) => {
    const pct = goalVal ? Math.min(150, Math.round((current / goalVal) * 100)) : 0
    const remaining = goalVal != null ? Math.round((goalVal - current) * 10) / 10 : null
    return {
      key: label,
      label,
      current,
      goal: goalVal,
      percent: Math.min(100, pct),
      onTarget: pct >= 90 && pct <= 110,
      remaining,
    }
  }
  return [
    make('Белки', totals?.proteinG ?? 0, goal?.proteinG),
    make('Жиры', totals?.fatG ?? 0, goal?.fatG),
    make('Углеводы', totals?.carbsG ?? 0, goal?.carbsG),
  ]
})

const measurementCells = computed(() => {
  const m = day.data?.measurements[0]
  const p = prevDayData.value?.measurements[0]
  const cell = (label: string, value: number | null | undefined, prevValue?: number | null) => {
    if (!value) return null
    const delta = prevValue != null ? Math.round((value - prevValue) * 10) / 10 : null
    return { label, value: String(value), delta }
  }
  return [
    cell('кг',        m?.weightKg,   p?.weightKg),
    cell('% жира',    m?.bodyFatPct, p?.bodyFatPct),
    cell('см талия',  m?.waistCm,    p?.waistCm),
    cell('см грудь',  m?.chestCm,    p?.chestCm),
    cell('см бёдра',  m?.hipsCm,     p?.hipsCm),
    cell('см бицепс', m?.bicepsCm,   p?.bicepsCm),
  ].filter(Boolean) as { label: string; value: string; delta: number | null }[]
})

const sprintChip = computed(() => {
  const goal = day.data?.goal
  if (!goal) return null

  const today = new Date().toISOString().slice(0, 10)
  const isToday = dateParam.value === today
  const dayN = daysBetween(goal.startDate, dateParam.value) + 1
  const typeLabel = GOAL_TYPE_LABEL[goal.type]

  if (goal.endDate) {
    const totalDays = daysBetween(goal.startDate, goal.endDate) + 1
    if (isToday) return `${typeLabel} · день ${dayN}/${totalDays}`
    return `${typeLabel} · ${formatShortDate(goal.startDate)} → ${formatShortDate(goal.endDate)} · день ${dayN}/${totalDays}`
  }
  if (isToday) return `${typeLabel} · день ${dayN}`
  return `${typeLabel} · с ${formatShortDate(goal.startDate)} · день ${dayN}`
})
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

    <!-- Tabs -->
    <div
      class="sticky top-[49px] z-10 flex px-4 pt-2 pb-2 gap-1"
      style="background: var(--color-bg); border-bottom: 1px solid var(--color-border)"
    >
      <button
        type="button"
        class="flex-1 py-2 text-sm rounded-[var(--radius-sm)] transition-colors"
        :style="activeTab === 'goal'
          ? 'background: var(--color-accent); color: white; font-weight: 600'
          : 'background: var(--color-surface-2); color: var(--color-text-2)'"
        @click="activeTab = 'goal'"
      >Цель</button>
      <button
        type="button"
        class="flex-1 py-2 text-sm rounded-[var(--radius-sm)] transition-colors"
        :style="activeTab === 'balance'
          ? 'background: var(--color-accent); color: white; font-weight: 600'
          : 'background: var(--color-surface-2); color: var(--color-text-2)'"
        @click="activeTab = 'balance'"
      >Реальный баланс</button>
    </div>

    <!-- Loading -->
    <div v-if="day.loading" class="flex flex-col gap-3 p-4">
      <div class="h-56 rounded-[var(--radius-md)] animate-pulse" style="background: var(--color-surface-2)" />
      <div class="h-20 rounded-[var(--radius-md)] animate-pulse" style="background: var(--color-surface-2)" />
      <div class="h-32 rounded-[var(--radius-md)] animate-pulse" style="background: var(--color-surface-2)" />
    </div>

    <!-- Content -->
    <div v-else-if="day.data" class="flex flex-col gap-3 p-4 pb-24">

      <!-- TAB: Цель -->
      <template v-if="activeTab === 'goal'">
        <ACard>
          <div class="flex flex-col items-center py-6 gap-3">
            <KcalRing
              :current="day.data.totals.kcal"
              :goal="day.data.goal?.kcal ?? 0"
            />
            <p
              v-if="prevDayData && day.data.totals.kcal !== undefined"
              class="text-xs"
              :style="{
                color: (day.data.totals.kcal - (prevDayData.totals?.kcal ?? 0)) === 0
                  ? 'var(--color-text-3)'
                  : (day.data.totals.kcal - (prevDayData.totals?.kcal ?? 0)) > 0
                    ? 'var(--color-red)'
                    : 'var(--color-accent)'
              }"
            >{{ deltaIcon(Math.round(day.data.totals.kcal - (prevDayData.totals?.kcal ?? 0))) }} {{ fmtDelta(Math.round(day.data.totals.kcal - (prevDayData.totals?.kcal ?? 0))) }} ккал vs {{ prevDayDate && prevDayDate === prevDate(dateParam) ? 'вчера' : formatShortDate(prevDayDate!) }}</p>
            <AModeBadge
              v-if="day.data.mode"
              :code="day.data.mode.code"
              :label="day.data.mode.label"
              :delta-kcal="day.data.mode.deltaKcal"
              clickable
              @click="showModeExplainer = true"
            />
            <p v-if="sprintChip" class="text-xs" style="color: var(--color-text-3)">
              {{ sprintChip }}
            </p>
          </div>
        </ACard>

        <div class="grid grid-cols-3 gap-2">
          <ACard v-for="macro in macroCards" :key="macro.key">
            <div class="px-3 py-3 text-center">
              <p class="text-xs mb-1" style="color: var(--color-text-3)">{{ macro.label }}</p>
              <p class="font-mono text-xl font-light leading-tight" style="color: var(--color-text)">
                {{ macro.current }}
              </p>
              <p v-if="macro.goal" class="text-xs mt-0.5" style="color: var(--color-text-3)">
                / {{ macro.goal }} г
              </p>
              <p
                v-if="macro.remaining !== null"
                class="text-[10px] mt-0.5"
                :style="{ color: macro.remaining < 0 ? 'var(--color-red)' : 'var(--color-accent)' }"
              >{{ macro.remaining > 0 ? '+' : '' }}{{ macro.remaining }} г</p>
              <div
                v-if="macro.goal"
                class="mt-2 h-1.5 rounded-full overflow-hidden"
                style="background: var(--color-surface-2)"
              >
                <div
                  class="h-full rounded-full"
                  :style="{
                    width: macro.percent + '%',
                    background: macro.onTarget ? 'var(--color-accent)' : 'var(--color-text-3)',
                    transition: 'width 400ms ease-out, background-color 200ms',
                  }"
                />
              </div>
            </div>
          </ACard>
        </div>
      </template>

      <!-- TAB: Реальный баланс -->
      <template v-else>
        <DayBalanceCard
          :tdee="day.data.tdee"
          :totals="day.data.totals"
          :day-entry="day.data.dayEntry"
          :workouts="day.data.workouts"
        />
      </template>

      <!-- Insights — visible on both tabs -->
      <DayInsights
        v-if="day.data.insights.length"
        :insights="day.data.insights"
        :date="dateParam"
      />

      <!-- Meals — common to both tabs -->
      <ACard>
        <div class="p-4">
          <div class="mb-3">
            <p class="text-sm font-semibold" style="color: var(--color-text)">Приёмы пищи</p>
          </div>
          <div v-if="day.data.meals.length === 0" class="text-sm py-2" style="color: var(--color-text-3)">
            Нет записей
          </div>
          <div v-else class="flex flex-col divide-y" style="border-color: var(--color-border)">
            <div v-for="meal in day.data.meals" :key="meal.uuid" class="flex items-center justify-between py-2.5">
              <button
                type="button"
                class="flex-1 text-left min-w-0 active:opacity-70 transition-opacity"
                @click="startEditMeal(meal)"
              >
                <p class="text-sm font-medium" style="color: var(--color-text)">{{ meal.name ?? '—' }}</p>
                <p class="text-xs" style="color: var(--color-text-3)">
                  {{ meal.slot }} · {{ meal.grams ? `${meal.grams} г · ` : '' }}{{ meal.kcal || 0 }} ккал
                </p>
                <p class="text-xs mt-0.5" style="color: var(--color-text-3)">
                  Б {{ meal.proteinG }} · Ж {{ meal.fatG }} · У {{ meal.carbsG }}
                </p>
              </button>
              <button
                type="button"
                class="p-2 text-xs flex-shrink-0"
                style="color: var(--color-text-3)"
                aria-label="Удалить"
                @click="mealToDelete = { uuid: meal.uuid, name: meal.name ?? 'приём пищи' }"
              >✕</button>
            </div>
          </div>
        </div>
      </ACard>

      <!-- Measurements -->
      <ACard>
        <div class="p-4">
          <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold" style="color: var(--color-text)">Замеры дня</p>
            <button
              v-if="day.data.measurements.length > 0"
              class="text-xs"
              style="color: var(--color-accent)"
              @click="showAddMeasurement = true"
            >Изменить</button>
          </div>
          <div v-if="measurementCells.length === 0" class="text-sm py-1" style="color: var(--color-text-3)">
            Нет замеров на этот день
          </div>
          <div v-else class="flex flex-wrap gap-x-4 gap-y-2">
            <div v-for="m in measurementCells" :key="m.label" class="flex items-baseline gap-1.5">
              <span class="font-mono text-xl font-light" style="color: var(--color-text)">{{ m.value }}</span>
              <span class="text-xs" style="color: var(--color-text-3)">{{ m.label }}</span>
              <span
                v-if="m.delta !== null"
                class="text-[10px]"
                :style="{ color: m.delta === 0 ? 'var(--color-text-3)' : m.delta > 0 ? 'var(--color-red)' : 'var(--color-accent)' }"
              >{{ deltaIcon(m.delta) }} {{ fmtDelta(m.delta) }}</span>
            </div>
          </div>
        </div>
      </ACard>

      <!-- Steps -->
      <ACard>
        <div class="p-4">
          <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold" style="color: var(--color-text)">Шаги</p>
            <button
              v-if="(day.data.dayEntry?.steps ?? 0) > 0"
              class="text-xs"
              style="color: var(--color-accent)"
              @click="showAddSteps = true"
            >Изменить</button>
          </div>
          <div v-if="(day.data.dayEntry?.steps ?? 0) === 0" class="text-sm py-1" style="color: var(--color-text-3)">
            Шаги не записаны
          </div>
          <div v-else class="flex items-baseline gap-3 flex-wrap">
            <div class="flex items-baseline gap-1.5">
              <span class="font-mono text-2xl font-light" style="color: var(--color-text)">
                {{ day.data.dayEntry!.steps!.toLocaleString('ru-RU') }}
              </span>
              <span class="text-xs" style="color: var(--color-text-3)">шагов</span>
              <span
                v-if="prevDayData?.dayEntry?.steps != null"
                class="text-[10px]"
                :style="{
                  color: (day.data.dayEntry!.steps! - prevDayData.dayEntry!.steps!) === 0
                    ? 'var(--color-text-3)'
                    : (day.data.dayEntry!.steps! - prevDayData.dayEntry!.steps!) > 0
                      ? 'var(--color-accent)'
                      : 'var(--color-red)'
                }"
              >{{ deltaIcon(day.data.dayEntry!.steps! - prevDayData.dayEntry!.steps!) }} {{ fmtDelta(day.data.dayEntry!.steps! - prevDayData.dayEntry!.steps!) }}</span>
            </div>
            <span style="color: var(--color-text-3)">·</span>
            <div class="flex items-baseline gap-1.5">
              <span class="font-mono text-base" style="color: var(--color-accent)">
                {{ day.data.tdee?.stepsKcal ?? 0 }}
              </span>
              <span class="text-xs" style="color: var(--color-text-3)">ккал сожжено</span>
            </div>
          </div>
        </div>
      </ACard>

      <!-- Workouts -->
      <ACard>
        <div class="p-4">
          <div class="mb-3">
            <p class="text-sm font-semibold" style="color: var(--color-text)">Тренировки</p>
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
              <button
                class="p-1 text-xs"
                style="color: var(--color-text-3)"
                @click="workoutToDelete = { uuid: w.uuid, name: w.name }"
              >✕</button>
            </div>
          </div>
        </div>
      </ACard>

    </div>

    <div v-else-if="day.error" class="flex flex-col items-center justify-center flex-1 gap-3 p-8">
      <p style="color: var(--color-text-2)">{{ day.error }}</p>
      <AButton @click="day.fetch()">Повторить</AButton>
    </div>

    <DayFab @add="openAdd" />

    <AddMealSheet v-model="showAddMeal" />
    <AddMealSheet v-model="showEditMeal" :meal="editingMeal" />
    <AddMeasurementSheet v-model="showAddMeasurement" />
    <AddStepsSheet v-model="showAddSteps" />
    <AddWorkoutSheet v-model="showAddWorkout" />
    <ModeExplainerModal
      v-model="showModeExplainer"
      :mode="day.data?.mode ?? null"
      :goal="day.data?.goal ?? null"
      :totals="day.data?.totals ?? null"
    />

    <AConfirm
      :model-value="mealToDelete !== null"
      title="Удалить приём пищи?"
      :message="mealToDelete ? `«${mealToDelete.name}» будет удалён.` : ''"
      confirm-label="Удалить"
      @update:model-value="(v) => { if (!v) mealToDelete = null }"
      @confirm="confirmDeleteMeal"
    />

    <AConfirm
      :model-value="workoutToDelete !== null"
      title="Удалить тренировку?"
      :message="workoutToDelete ? `«${workoutToDelete.name}» будет удалена.` : ''"
      confirm-label="Удалить"
      @update:model-value="(v) => { if (!v) workoutToDelete = null }"
      @confirm="confirmDeleteWorkout"
    />
  </div>
</template>
