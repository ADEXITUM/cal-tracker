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

type Tab = 'calendar' | 'weight' | 'kcal' | 'composition' | 'activity'
type Period = 7 | 30 | 90 | 365

const router = useRouter()
const tab = ref<Tab>('calendar')
const period = ref<Period>(30)
const summary = ref<StatsSummary | null>(null)
const series = ref<Record<string, StatsSeries>>({})
const loading = ref(false)
const calendarDays = ref<DaySummary[]>([])
const calendarMetric = ref<'kcal' | 'tracked'>('kcal')

const tabOptions: { value: Tab; label: string }[] = [
  { value: 'calendar', label: 'Календарь' },
  { value: 'weight', label: 'Вес' },
  { value: 'kcal', label: 'КБЖУ' },
  { value: 'composition', label: 'Состав' },
  { value: 'activity', label: 'Активность' },
]

const calendarMetricOptions: { value: 'kcal' | 'tracked'; label: string }[] = [
  { value: 'kcal', label: 'Δ от цели' },
  { value: 'tracked', label: 'Записано' },
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
  }
}

async function loadForTab() {
  const metricsByTab: Record<Exclude<Tab, 'calendar'>, StatsMetric[]> = {
    weight:      ['weight'],
    kcal:        ['kcal', 'protein_g', 'fat_g', 'carbs_g'],
    composition: ['body_fat_pct', 'muscle_mass_kg'],
    activity:    ['steps'],
  }
  if (tab.value === 'calendar') return
  await Promise.all(metricsByTab[tab.value].map(loadSeries))
}

function pickDay(date: string) {
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
        <ASegmented v-model="calendarMetric" :options="calendarMetricOptions" />
        <ACard>
          <div class="p-4">
            <DayHeatmap :days="calendarDays" :weeks="calendarWeeks" :metric="calendarMetric" @pick="pickDay" />
            <div v-if="calendarMetric === 'kcal'" class="mt-4 flex flex-wrap items-center gap-3 text-xs" style="color: var(--color-text-3)">
              <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background: var(--color-green)" /> ±100</span>
              <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background: var(--color-accent)" /> ±250</span>
              <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background: var(--color-yellow)" /> ±500</span>
              <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background: var(--color-red)" /> &gt;500</span>
            </div>
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
            <LineChart
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
            <BarChart :points="series.kcal?.points ?? []" unit="ккал" />
          </div>
        </ACard>
        <ACard>
          <div class="p-3">
            <p class="text-xs mb-2" style="color: var(--color-text-3)">Белки</p>
            <BarChart :points="series.protein_g?.points ?? []" unit="г" :height="180" />
          </div>
        </ACard>
        <ACard>
          <div class="p-3">
            <p class="text-xs mb-2" style="color: var(--color-text-3)">Жиры</p>
            <BarChart :points="series.fat_g?.points ?? []" unit="г" :height="180" />
          </div>
        </ACard>
        <ACard>
          <div class="p-3">
            <p class="text-xs mb-2" style="color: var(--color-text-3)">Углеводы</p>
            <BarChart :points="series.carbs_g?.points ?? []" unit="г" :height="180" />
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
            <LineChart
              :raw="series.body_fat_pct?.points ?? []"
              :smoothed="series.body_fat_pct?.rollingAvg7d ?? []"
              unit="%"
            />
          </div>
        </ACard>
        <ACard>
          <div class="p-3">
            <p class="text-xs mb-2" style="color: var(--color-text-3)">Мышечная масса</p>
            <LineChart
              :raw="series.muscle_mass_kg?.points ?? []"
              :smoothed="series.muscle_mass_kg?.rollingAvg7d ?? []"
              unit="кг"
            />
          </div>
        </ACard>
      </template>

      <!-- Activity tab -->
      <template v-else>
        <ACard>
          <div class="px-4 py-3">
            <p class="text-xs" style="color: var(--color-text-3)">Активных дней</p>
            <p class="font-mono text-lg" style="color: var(--color-text)">{{ summary?.activeDaysPct ?? 0 }}%</p>
          </div>
        </ACard>
        <ACard>
          <div class="p-3">
            <p class="text-xs mb-2" style="color: var(--color-text-3)">Шаги</p>
            <BarChart :points="series.steps?.points ?? []" unit="шагов" />
          </div>
        </ACard>
      </template>

    </div>
  </div>
</template>
