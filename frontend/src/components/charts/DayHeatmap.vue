<script setup lang="ts">
import { computed } from 'vue'
import type { DaySummary } from '@/api/days'

const props = defineProps<{
  days: DaySummary[]
  weeks: number
  metric: 'kcal' | 'tracked'
}>()

const emit = defineEmits<{ pick: [date: string] }>()

const today = new Date()
today.setHours(12, 0, 0, 0)

const allDates = computed(() => {
  // last `weeks` × 7 days, ending today
  const totalDays = props.weeks * 7
  // align to Monday-start week containing today
  const startMs = today.getTime() - (totalDays - 1) * 86400000
  const out: string[] = []
  for (let i = 0; i < totalDays; i++) {
    const d = new Date(startMs + i * 86400000)
    out.push(d.toISOString().slice(0, 10))
  }
  return out
})

const byDate = computed(() => {
  const m = new Map<string, DaySummary>()
  props.days.forEach(d => m.set(d.date, d))
  return m
})

function colorFor(date: string): string {
  const data = byDate.value.get(date)
  if (props.metric === 'tracked') {
    if (!data) return 'var(--color-surface-2)'
    if (data.totals.kcal === 0 && data.weightKg === null) return 'var(--color-surface-2)'
    return 'var(--color-accent)'
  }
  // kcal vs goal (delta)
  if (!data || data.deltaFromGoal === null) return 'var(--color-surface-2)'
  const d = data.deltaFromGoal
  if (Math.abs(d) <= 100) return 'var(--color-green)'
  if (Math.abs(d) <= 250) return 'var(--color-accent)'
  if (Math.abs(d) <= 500) return 'var(--color-yellow)'
  return 'var(--color-red)'
}

function isFuture(date: string): boolean {
  return date > new Date().toISOString().slice(0, 10)
}

// Group into columns of 7 (weeks). The first cell goes into the first column.
const columns = computed(() => {
  const cols: string[][] = []
  const dates = allDates.value
  for (let i = 0; i < dates.length; i += 7) {
    cols.push(dates.slice(i, i + 7))
  }
  return cols
})

function shortLabel(date: string): string {
  return new Date(date + 'T12:00:00').toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
}
</script>

<template>
  <div class="overflow-x-auto">
    <div class="flex gap-1">
      <div v-for="(col, ci) in columns" :key="ci" class="flex flex-col gap-1">
        <button
          v-for="date in col"
          :key="date"
          type="button"
          class="w-5 h-5 rounded-sm transition-transform active:scale-90"
          :class="isFuture(date) ? 'opacity-30 cursor-not-allowed' : 'cursor-pointer hover:scale-110'"
          :style="{ background: colorFor(date) }"
          :title="shortLabel(date)"
          :disabled="isFuture(date)"
          @click="emit('pick', date)"
        />
      </div>
    </div>
  </div>
</template>
