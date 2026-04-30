<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { statsApi, type StatsMetric, type StatsSummary, type StatsSeries } from '@/api/stats'
import { daysApi, type DaySummary } from '@/api/days'
import ACard from '@/components/ui/ACard.vue'
import ASegmented from '@/components/ui/ASegmented.vue'
import LineChart from '@/components/charts/LineChart.vue'
import BarChart from '@/components/charts/BarChart.vue'
import ComparisonCard from '@/components/stats/ComparisonCard.vue'
import DayHeatmap from '@/components/charts/DayHeatmap.vue'

type Tab = 'calendar' | 'weight' | 'kcal' | 'composition'
type Period = 7 | 30 | 90 | 365

const router = useRouter()
const tab = ref<Tab>('calendar')
const period = ref<Period>(30)
const summary = ref<StatsSummary | null>(null)
const series = ref<Record<string, StatsSeries>>({})
const loading = ref(false)
const seriesLoading = ref(false)
const calendarDays = ref<DaySummary[]>([])
const tabOptions: { value: Tab; label: string }[] = [
  { value: 'calendar', label: 'Календарь' },
  { value: 'weight', label: 'Вес' },
  { value: 'kcal', label: 'КБЖУ' },
  { value: 'composition', label: 'Состав' },
]

const periodOptions: { value: Period; label: string }[] = [
  { value: 7, label: '7 дней' },
  { value: 30, label: '30 дней' },
  { value: 90, label: '3 мес' },
  { value: 365, label: '1 год' },
]

const range = computed(() => {
  const to = new Date().toISOString().slice(0, 10)
  const from = new Date(Date.now() - period.value * 86400000).toISOString().slice(0, 10)
  return { from, to }
})

async function loadSeries(metric: StatsMetric) {
  if (series.value[metric]) return
  const res = await statsApi.series(metric, range.value.from, range.value.to)
  series.value[metric] = res.data
}

async function reload() {
  loading.value = true
  seriesLoading.value = true
  series.value = {}
  try {
    if (tab.value === 'calendar') {
      const res = await daysApi.list(range.value.from, range.value.to)
      calendarDays.value = res.data
    } else {
      const sumRes = await statsApi.summary(range.value.from, range.value.to)
      summary.value = sumRes.data
      await loadForTab()
    }
  } finally {
    loading.value = false
    seriesLoading.value = false
  }
}

async function loadForTab() {
  const metricsByTab: Record<Exclude<Tab, 'calendar'>, StatsMetric[]> = {
    weight:      ['weight'],
    kcal:        ['kcal', 'protein_g', 'fat_g', 'carbs_g', 'steps'],
    composition: ['body_fat_pct'],
  }
  if (tab.value === 'calendar') return
  await Promise.all(metricsByTab[tab.value].map(loadSeries))
}

function navigateToDay(date: string) {
  router.push({ name: 'day', params: { date } })
}

const calendarWeeks = computed(() => Math.ceil(period.value / 7))

watch([tab, period], () => { reload() })
onMounted(() => reload())
</script>

<template>
  <div class="flex flex-col min-h-svh" style="background: var(--color-bg)">
    <header
      class="sticky top-0 z-10 flex flex-col gap-3 px-4 py-3"
      style="background: var(--color-bg); border-bottom: 1px solid var(--color-border)"
    >
      <h1 class="text-base font-semibold" style="color: var(--color-text)">Прогресс</h1>
      <ASegmented v-model="tab" :options="tabOptions" />
      <ASegmented v-model="period" :options="periodOptions" />
    </header>

    <div class="p-4 pb-24 flex flex-col gap-3">

      <!-- Loading -->
      <div v-if="loading && !summary" class="flex flex-col gap-3">
        <div class="h-32 rounded-[var(--radius-md)] animate-pulse" style="background: var(--color-surface-2)" />
        <div class="h-60 rounded-[var(--radius-md)] animate-pulse" style="background: var(--color-surface-2)" />
      </div>

      <!-- Calendar tab -->
      <template v-else-if="tab === 'calendar'">
        <ACard>
          <div class="p-4">
            <DayHeatmap :days="calendarDays" :weeks="calendarWeeks" @navigate="navigateToDay" />
          </div>
        </ACard>
      </template>

      <!-- Weight tab -->
      <template v-else-if="tab === 'weight'">
        <ComparisonCard
          label="Вес"
          :start="summary?.weight.start ?? null"
          :end="summary?.weight.end ?? null"
          unit="кг"
          good="down"
        />
        <ACard v-if="summary?.weight.trendKgPerWeek !== null && summary?.weight.trendKgPerWeek !== undefined">
          <div class="px-4 py-3">
            <p class="text-xs" style="color: var(--color-text-3)">Темп</p>
            <p class="font-mono text-base" style="color: var(--color-text)">
              {{ summary.weight.trendKgPerWeek > 0 ? '+' : '' }}{{ summary.weight.trendKgPerWeek }} кг/нед
            </p>
          </div>
        </ACard>
        <ACard>
          <div class="p-3">
            <p class="text-xs mb-2" style="color: var(--color-text-3)">График веса</p>
            <div v-if="seriesLoading" class="h-60 animate-pulse rounded-[var(--radius-sm)]" style="background: var(--color-surface-2)" />
            <LineChart
              v-else
              :raw="series.weight?.points ?? []"
              :smoothed="series.weight?.rollingAvg7d ?? []"
              unit="кг"
            />
          </div>
        </ACard>
      </template>

      <!-- Kcal/macros tab -->
      <template v-else-if="tab === 'kcal'">
        <ACard v-if="summary?.kcal">
          <div class="px-4 py-3 grid grid-cols-2 gap-3">
            <div>
              <p class="text-xs" style="color: var(--color-text-3)">Среднее ккал</p>
              <p class="font-mono text-lg" style="color: var(--color-text)">{{ summary.kcal.avg ?? '—' }}</p>
            </div>
            <div>
              <p class="text-xs" style="color: var(--color-text-3)">Дней с записью</p>
              <p class="font-mono text-lg" style="color: var(--color-text)">{{ summary.kcal.daysTracked }}</p>
            </div>
            <div v-if="summary.kcal.vsGoal !== null">
              <p class="text-xs" style="color: var(--color-text-3)">vs цель (ср.)</p>
              <p
                class="font-mono text-lg"
                :style="{ color: Math.abs(summary.kcal.vsGoal) < 100 ? 'var(--color-text)' : 'var(--color-accent)' }"
              >
                {{ summary.kcal.vsGoal > 0 ? '+' : '' }}{{ summary.kcal.vsGoal }}
              </p>
            </div>
          </div>
        </ACard>
        <ACard>
          <div class="p-3">
            <p class="text-xs mb-2" style="color: var(--color-text-3)">Калории по дням</p>
            <div v-if="seriesLoading" class="h-60 animate-pulse rounded-[var(--radius-sm)]" style="background: var(--color-surface-2)" />
            <BarChart v-else :points="series.kcal?.points ?? []" unit="ккал" />
          </div>
        </ACard>
        <ACard>
          <div class="p-3">
            <p class="text-xs mb-2" style="color: var(--color-text-3)">Белки</p>
            <div v-if="seriesLoading" class="h-44 animate-pulse rounded-[var(--radius-sm)]" style="background: var(--color-surface-2)" />
            <BarChart v-else :points="series.protein_g?.points ?? []" unit="г" :height="180" />
          </div>
        </ACard>
        <ACard>
          <div class="p-3">
            <p class="text-xs mb-2" style="color: var(--color-text-3)">Жиры</p>
            <div v-if="seriesLoading" class="h-44 animate-pulse rounded-[var(--radius-sm)]" style="background: var(--color-surface-2)" />
            <BarChart v-else :points="series.fat_g?.points ?? []" unit="г" :height="180" />
          </div>
        </ACard>
        <ACard>
          <div class="p-3">
            <p class="text-xs mb-2" style="color: var(--color-text-3)">Углеводы</p>
            <div v-if="seriesLoading" class="h-44 animate-pulse rounded-[var(--radius-sm)]" style="background: var(--color-surface-2)" />
            <BarChart v-else :points="series.carbs_g?.points ?? []" unit="г" :height="180" />
          </div>
        </ACard>
        <ACard>
          <div class="p-3">
            <p class="text-xs mb-2" style="color: var(--color-text-3)">Шаги по дням</p>
            <div v-if="seriesLoading" class="h-44 animate-pulse rounded-[var(--radius-sm)]" style="background: var(--color-surface-2)" />
            <BarChart v-else :points="series.steps?.points ?? []" unit="шагов" :height="180" />
          </div>
        </ACard>
      </template>

      <!-- Composition tab -->
      <template v-else-if="tab === 'composition'">
        <ComparisonCard
          label="% жира"
          :start="summary?.bodyFatPct.start ?? null"
          :end="summary?.bodyFatPct.end ?? null"
          unit="%"
          good="down"
        />
        <ACard>
          <div class="p-3">
            <p class="text-xs mb-2" style="color: var(--color-text-3)">% жира</p>
            <div v-if="seriesLoading" class="h-60 animate-pulse rounded-[var(--radius-sm)]" style="background: var(--color-surface-2)" />
            <LineChart
              v-else
              :raw="series.body_fat_pct?.points ?? []"
              :smoothed="series.body_fat_pct?.rollingAvg7d ?? []"
              unit="%"
            />
          </div>
        </ACard>
      </template>

    </div>
  </div>
</template>
