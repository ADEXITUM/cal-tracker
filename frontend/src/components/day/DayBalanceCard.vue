<script setup lang="ts">
import { computed, ref } from 'vue'
import ACard from '@/components/ui/ACard.vue'
import type { DayTdee, Totals, DayEntry, Workout } from '@/types/api'

const props = defineProps<{
  tdee: DayTdee | null
  totals: Totals
  dayEntry: DayEntry | null
  workouts: Workout[]
}>()

const showFormulas = ref(false)

const hasAnyActivity = computed(() => {
  const steps = props.dayEntry?.steps ?? 0
  return steps > 0 || props.workouts.length > 0
})

const hasAnyData = computed(() => hasAnyActivity.value || props.totals.kcal > 0)

const balance = computed(() => {
  if (!props.tdee) return 0
  return Math.round(props.totals.kcal - props.tdee.total)
})

function fmtSigned(val: number): string {
  return (val > 0 ? '+' : '') + val
}
</script>

<template>
  <ACard>
    <div v-if="!hasAnyData" class="p-4 text-center">
      <p class="text-sm" style="color: var(--color-text-2)">Запиши еду или активность,</p>
      <p class="text-sm" style="color: var(--color-text-2)">чтобы увидеть баланс дня</p>
    </div>

    <div v-else-if="tdee" class="p-4 flex flex-col gap-4">
      <!-- Energy balance summary -->
      <div class="flex flex-col items-center gap-1">
        <p class="text-xs" style="color: var(--color-text-3)">Баланс дня</p>
        <p
          class="font-mono text-3xl font-light"
          :style="{ color: balance < 0 ? 'var(--color-accent)' : balance > 0 ? 'var(--color-red)' : 'var(--color-text)' }"
        >
          {{ fmtSigned(balance) }} ккал
        </p>
        <p class="text-xs" style="color: var(--color-text-3)">
          {{ balance < 0 ? 'Дефицит' : balance > 0 ? 'Профицит' : 'Поддержка' }}
        </p>
      </div>

      <!-- Breakdown -->
      <div class="flex flex-col gap-2">
        <p class="text-xs font-medium" style="color: var(--color-text-3)">Расход</p>

        <div class="flex items-baseline justify-between py-1.5 border-b" style="border-color: var(--color-border)">
          <span class="text-sm" style="color: var(--color-text-2)">База (BMR × 1.2)</span>
          <span class="font-mono text-sm" style="color: var(--color-text)">{{ tdee.baseKcal }}</span>
        </div>

        <div class="flex items-baseline justify-between py-1.5 border-b" style="border-color: var(--color-border)">
          <span class="text-sm" style="color: var(--color-text-2)">
            Шаги
            <span v-if="dayEntry?.steps" class="text-xs" style="color: var(--color-text-3)">
              ({{ dayEntry.steps.toLocaleString('ru-RU') }})
            </span>
          </span>
          <span class="font-mono text-sm" style="color: var(--color-text)">+{{ tdee.stepsKcal }}</span>
        </div>

        <div class="flex items-baseline justify-between py-1.5 border-b" style="border-color: var(--color-border)">
          <span class="text-sm" style="color: var(--color-text-2)">
            Тренировки
            <span v-if="workouts.length" class="text-xs" style="color: var(--color-text-3)">
              ({{ workouts.length }})
            </span>
          </span>
          <span class="font-mono text-sm" style="color: var(--color-text)">+{{ tdee.workoutsKcal }}</span>
        </div>

        <div class="flex items-baseline justify-between py-1.5 mt-1">
          <span class="text-sm font-medium" style="color: var(--color-text)">Сжёг всего</span>
          <span class="font-mono text-base font-medium" style="color: var(--color-text)">{{ tdee.total }}</span>
        </div>

        <div class="flex items-baseline justify-between py-1.5">
          <span class="text-sm" style="color: var(--color-text-2)">Съел</span>
          <span class="font-mono text-base" style="color: var(--color-text)">{{ Math.round(totals.kcal) }}</span>
        </div>
      </div>

      <!-- Hint when activity is empty -->
      <div
        v-if="!hasAnyActivity"
        class="text-xs p-2 rounded-[var(--radius-sm)] text-center"
        style="background: var(--color-surface-2); color: var(--color-text-3)"
      >
        Шаги и тренировки не записаны — расход показан только из базы.
      </div>

      <!-- Formulas toggle -->
      <button
        type="button"
        class="text-xs underline self-center"
        style="color: var(--color-text-3)"
        @click="showFormulas = !showFormulas"
      >
        {{ showFormulas ? 'скрыть формулы' : 'как считается?' }}
      </button>

      <div
        v-if="showFormulas"
        class="text-xs p-3 rounded-[var(--radius-sm)] flex flex-col gap-1"
        style="background: var(--color-surface-2); color: var(--color-text-2)"
      >
        <p><strong>База</strong> = BMR × 1.2 (минимальный расход в покое + быт)</p>
        <p><strong>BMR</strong> = формула Mifflin-St Jeor по полу/росту/весу/возрасту</p>
        <p><strong>Шаги</strong> = шаги × вес × 0.0005</p>
        <p><strong>Тренировки</strong> = что ты сам указал в записи</p>
      </div>
    </div>
  </ACard>
</template>
