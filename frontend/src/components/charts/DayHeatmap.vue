<script setup lang="ts">
import { ref, computed } from 'vue'
import type { DaySummary } from '@/api/days'

const props = defineProps<{
  days: DaySummary[]
  weeks: number
}>()

const emit = defineEmits<{ navigate: [date: string] }>()

const today = new Date()
today.setHours(12, 0, 0, 0)
const todayStr = today.toISOString().slice(0, 10)

// Selected cell for tooltip
const selected = ref<string | null>(null)

// All dates: weeks × 7, ending today, row-major (week rows, dow columns)
// Row 0 = oldest week, col 0 = Mon
const grid = computed(() => {
  const totalDays = props.weeks * 7
  const startMs = today.getTime() - (totalDays - 1) * 86400000
  const rows: string[][] = Array.from({ length: props.weeks }, () => [])
  for (let i = 0; i < totalDays; i++) {
    const d = new Date(startMs + i * 86400000)
    const week = Math.floor(i / 7)
    rows[week].push(d.toISOString().slice(0, 10))
  }
  return rows
})

// Column headers: day-of-week labels from first row
const dowLabels = computed(() => {
  if (!grid.value.length) return []
  return grid.value[0].map(date =>
    new Date(date + 'T12:00:00').toLocaleDateString('ru-RU', { weekday: 'short' })
  )
})

const byDate = computed(() => {
  const m = new Map<string, DaySummary>()
  props.days.forEach(d => m.set(d.date, d))
  return m
})

function colorFor(date: string): string {
  const data = byDate.value.get(date)
  if (!data || data.deltaFromGoal === null) return 'var(--color-surface-3)'
  const d = data.deltaFromGoal
  if (Math.abs(d) <= 100) return 'var(--color-green)'
  if (Math.abs(d) <= 250) return 'var(--color-accent)'
  if (Math.abs(d) <= 500) return 'var(--color-yellow)'
  return 'var(--color-red)'
}

function isFuture(date: string): boolean {
  return date > todayStr
}

function isToday(date: string): boolean {
  return date === todayStr
}

function tap(date: string) {
  if (isFuture(date)) return
  selected.value = selected.value === date ? null : date
}

function navigate() {
  if (selected.value) {
    emit('navigate', selected.value)
    selected.value = null
  }
}

function dismiss() {
  selected.value = null
}

function tooltipData(date: string): { kcal: number | null; weight: string | null; delta: number | null } | null {
  const d = byDate.value.get(date)
  if (!d) return null
  return {
    kcal: d.totals.kcal || null,
    weight: d.weightKg ? `${d.weightKg} кг` : null,
    delta: d.deltaFromGoal,
  }
}

function formatDate(date: string): string {
  return new Date(date + 'T12:00:00').toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' })
}

function formatDelta(delta: number | null): string {
  if (delta === null) return '—'
  return (delta > 0 ? '+' : '') + delta + ' ккал'
}
</script>

<template>
  <!-- tap outside to dismiss -->
  <div @click.self="dismiss">

    <!-- DoW column headers -->
    <div class="flex gap-1 mb-1 pl-0">
      <div
        v-for="label in dowLabels" :key="label"
        class="flex-1 text-center"
        style="font-size: 10px; color: var(--color-text-3); min-width: 0"
      >{{ label }}</div>
    </div>

    <!-- Grid: rows = weeks, cols = days of week -->
    <div class="flex flex-col gap-1">
      <div v-for="(row, ri) in grid" :key="ri" class="flex gap-1">
        <button
          v-for="date in row"
          :key="date"
          type="button"
          class="flex-1 aspect-square rounded-[4px] transition-all duration-150"
          :class="[
            isFuture(date) ? 'opacity-20 cursor-default' : 'cursor-pointer',
            selected && selected !== date ? 'opacity-30' : '',
            isToday(date) ? 'ring-2 ring-offset-1' : '',
          ]"
          :style="{
            background: colorFor(date),
            ringColor: 'var(--color-accent)',
            ringOffsetColor: 'var(--color-bg)',
            transform: selected === date ? 'scale(1.15)' : '',
          }"
          :disabled="isFuture(date)"
          @click.stop="tap(date)"
        />
      </div>
    </div>

    <!-- Inline tooltip -->
    <Transition name="tip">
      <div
        v-if="selected"
        class="mt-4 rounded-[var(--radius-md)] px-4 py-3 flex items-center justify-between gap-3"
        style="background: var(--color-surface-2); border: 1px solid var(--color-border)"
        @click.stop
      >
        <div class="min-w-0">
          <p class="text-sm font-semibold" style="color: var(--color-text)">{{ formatDate(selected) }}</p>
          <div class="flex gap-3 mt-1 flex-wrap">
            <template v-if="tooltipData(selected)">
              <span class="text-xs" style="color: var(--color-text-2)">
                {{ tooltipData(selected)!.kcal ? tooltipData(selected)!.kcal + ' ккал' : 'нет записей' }}
              </span>
              <span v-if="tooltipData(selected)!.weight" class="text-xs" style="color: var(--color-text-2)">
                {{ tooltipData(selected)!.weight }}
              </span>
              <span
                v-if="tooltipData(selected)!.delta !== null"
                class="text-xs font-medium"
                :style="{ color: Math.abs(tooltipData(selected)!.delta!) <= 100 ? 'var(--color-green)' : 'var(--color-accent)' }"
              >
                {{ formatDelta(tooltipData(selected)!.delta) }}
              </span>
            </template>
            <span v-else class="text-xs" style="color: var(--color-text-3)">нет данных</span>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <button
            class="text-xs px-3 py-1.5 rounded-[var(--radius-sm)] font-medium"
            style="background: var(--color-accent); color: #fff"
            @click="navigate"
          >Открыть</button>
          <button
            class="text-xs px-2 py-1.5"
            style="color: var(--color-text-3)"
            @click="dismiss"
          >✕</button>
        </div>
      </div>
    </Transition>

    <!-- Legend -->
    <div class="mt-4 flex flex-wrap items-center gap-3 text-xs" style="color: var(--color-text-3)">
      <span class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded-sm" style="background: var(--color-green)" /> ±100 ккал
      </span>
      <span class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded-sm" style="background: var(--color-accent)" /> ±250
      </span>
      <span class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded-sm" style="background: var(--color-yellow)" /> ±500
      </span>
      <span class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded-sm" style="background: var(--color-red)" /> &gt;500
      </span>
      <span class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded-sm" style="background: var(--color-surface-3)" /> нет цели
      </span>
    </div>
  </div>
</template>

<style scoped>
.tip-enter-active, .tip-leave-active {
  transition: opacity 150ms ease, transform 150ms ease;
}
.tip-enter-from, .tip-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
