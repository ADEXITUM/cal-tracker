<script setup lang="ts">
import { computed } from 'vue'
import VueApexCharts from 'vue3-apexcharts'

const props = defineProps<{
  raw: { date: string; value: number | null }[]
  smoothed?: { date: string; value: number | null }[]
  unit?: string
  height?: number
  color?: string
}>()

const accentColor = '#5B8DEF'

const hasData = computed(() => props.raw.some(p => p.value !== null))

const series = computed(() => {
  const out: { name: string; data: { x: number; y: number | null }[]; color?: string }[] = [
    {
      name: 'Замеры',
      data: props.raw.map(p => ({ x: new Date(p.date + 'T12:00:00').getTime(), y: p.value })),
      color: 'rgba(150,150,150,0.5)',
    },
  ]
  if (props.smoothed?.length) {
    out.push({
      name: '7d среднее',
      data: props.smoothed.map(p => ({ x: new Date(p.date + 'T12:00:00').getTime(), y: p.value })),
      color: props.color ?? accentColor,
    })
  }
  return out
})

const options = computed(() => ({
  chart: {
    type: 'line' as const,
    toolbar: { show: false },
    zoom: { enabled: false },
    animations: { enabled: true, easing: 'easeout', speed: 400 },
    fontFamily: 'inherit',
  },
  stroke: { curve: 'smooth' as const, width: [2, 3] },
  markers: { size: [3, 0] },
  xaxis: {
    type: 'datetime' as const,
    labels: { datetimeUTC: false, style: { colors: 'var(--color-text-3)' } },
    axisBorder: { color: 'var(--color-border)' },
    axisTicks: { color: 'var(--color-border)' },
  },
  yaxis: {
    labels: {
      style: { colors: 'var(--color-text-3)' },
      formatter: (v: number) => props.unit ? `${v} ${props.unit}` : `${v}`,
    },
  },
  grid: { borderColor: 'var(--color-border)', strokeDashArray: 3 },
  legend: { show: false },
  tooltip: {
    theme: 'dark',
    x: { format: 'd MMM yyyy' },
    style: { fontSize: '12px' },
  },
  noData: {
    text: 'Нет данных',
    style: { color: 'var(--color-text-3)' },
  },
}))
</script>

<template>
  <div v-if="!hasData" class="flex items-center justify-center text-sm" :style="{ height: `${height ?? 240}px`, color: 'var(--color-text-3)' }">
    Нет данных за период
  </div>
  <VueApexCharts
    v-else
    type="line"
    :height="height ?? 240"
    :options="options"
    :series="series"
  />
</template>
