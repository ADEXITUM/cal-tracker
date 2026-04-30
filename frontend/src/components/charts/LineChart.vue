<script setup lang="ts">
import { computed } from 'vue'
import VueApexCharts from 'vue3-apexcharts'

interface Point { date: string; value: number | null }

const props = defineProps<{
  raw: Point[]
  smoothed?: Point[]
  unit?: string
  height?: number
  color?: string
  // Optional second metric on a secondary y-axis (e.g. body fat % alongside weight)
  secondary?: { points: Point[]; unit?: string; label?: string; color?: string } | null
}>()

const accentColor = '#5B8DEF'
const secondaryColor = '#FF8C42'

const hasData = computed(() =>
  props.raw.some(p => p.value !== null) ||
  (props.secondary?.points.some(p => p.value !== null) ?? false)
)

const series = computed(() => {
  const out: { name: string; data: { x: number; y: number | null }[]; color?: string }[] = []

  if (props.raw.length || props.smoothed?.length) {
    out.push({
      name: 'Замеры',
      data: props.raw.map(p => ({ x: new Date(p.date + 'T12:00:00').getTime(), y: p.value })),
      color: 'rgba(150,150,150,0.5)',
    })
    if (props.smoothed?.length) {
      out.push({
        name: '7д среднее',
        data: props.smoothed.map(p => ({ x: new Date(p.date + 'T12:00:00').getTime(), y: p.value })),
        color: props.color ?? accentColor,
      })
    }
  }

  if (props.secondary?.points.length) {
    out.push({
      name: props.secondary.label ?? 'Доп.',
      data: props.secondary.points.map(p => ({ x: new Date(p.date + 'T12:00:00').getTime(), y: p.value })),
      color: props.secondary.color ?? secondaryColor,
    })
  }

  return out
})

const yaxes = computed(() => {
  const primaryUnit = props.unit
  const list: object[] = [
    {
      seriesName: 'Замеры',
      labels: {
        style: { colors: 'var(--color-text-3)' },
        formatter: (v: number) => primaryUnit ? `${v} ${primaryUnit}` : `${v}`,
      },
      axisBorder: { show: false },
    },
  ]
  // ApexCharts ties yaxis blocks to series by index. The smoothed series shares
  // the primary axis; the secondary metric (if any) gets its own.
  if (props.smoothed?.length) {
    list.push({
      seriesName: 'Замеры',
      show: false,
    })
  }
  if (props.secondary?.points.length) {
    const u = props.secondary.unit
    list.push({
      seriesName: props.secondary.label ?? 'Доп.',
      opposite: true,
      labels: {
        style: { colors: props.secondary.color ?? secondaryColor },
        formatter: (v: number) => u ? `${v} ${u}` : `${v}`,
      },
      axisBorder: { show: false },
    })
  }
  return list
})

const options = computed(() => ({
  chart: {
    type: 'line' as const,
    toolbar: { show: false },
    zoom: { enabled: false },
    animations: { enabled: true, easing: 'easeout', speed: 400 },
    fontFamily: 'inherit',
  },
  stroke: {
    curve: 'smooth' as const,
    width: props.smoothed?.length ? [2, 3, 3] : (props.secondary ? [2, 3] : [2]),
  },
  markers: {
    size: props.smoothed?.length ? [3, 0, 3] : (props.secondary ? [3, 3] : [3]),
  },
  xaxis: {
    type: 'datetime' as const,
    labels: { datetimeUTC: false, style: { colors: 'var(--color-text-3)' } },
    axisBorder: { color: 'var(--color-border)' },
    axisTicks: { color: 'var(--color-border)' },
  },
  yaxis: yaxes.value,
  grid: { borderColor: 'var(--color-border)', strokeDashArray: 3 },
  legend: {
    show: !!props.secondary,
    labels: { colors: 'var(--color-text-3)' },
  },
  tooltip: {
    theme: 'dark',
    x: { format: 'd MMM yyyy' },
    style: { fontSize: '12px' },
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
