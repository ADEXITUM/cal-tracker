<script setup lang="ts">
import { computed } from 'vue'
import VueApexCharts from 'vue3-apexcharts'

const props = defineProps<{
  points: { date: string; value: number | null }[]
  unit?: string
  goal?: number | null
  height?: number
  color?: string
}>()

const accentColor = '#5B8DEF'

const series = computed(() => [{
  name: 'Значение',
  data: props.points.map(p => ({ x: new Date(p.date + 'T12:00:00').getTime(), y: p.value })),
}])

const options = computed(() => ({
  chart: {
    type: 'bar' as const,
    toolbar: { show: false },
    zoom: { enabled: false },
    animations: { enabled: true, easing: 'easeout', speed: 400 },
    fontFamily: 'inherit',
  },
  plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
  dataLabels: { enabled: false },
  colors: [props.color ?? accentColor],
  xaxis: {
    type: 'datetime' as const,
    labels: { datetimeUTC: false, style: { colors: 'var(--color-text-3)' } },
    axisBorder: { color: 'var(--color-border)' },
    axisTicks: { color: 'var(--color-border)' },
  },
  yaxis: {
    labels: {
      style: { colors: 'var(--color-text-3)' },
      formatter: (v: number) => props.unit ? `${Math.round(v)} ${props.unit}` : `${Math.round(v)}`,
    },
  },
  grid: { borderColor: 'var(--color-border)', strokeDashArray: 3 },
  legend: { show: false },
  tooltip: {
    theme: 'light',
    x: { format: 'd MMM yyyy' },
  },
  annotations: props.goal ? {
    yaxis: [{
      y: props.goal,
      borderColor: 'var(--color-text-3)',
      strokeDashArray: 4,
      label: {
        text: `Цель: ${props.goal}`,
        style: { color: 'var(--color-text-3)', background: 'transparent' },
      },
    }],
  } : {},
  noData: {
    text: 'Нет данных',
    style: { color: 'var(--color-text-3)' },
  },
}))
</script>

<template>
  <VueApexCharts
    type="bar"
    :height="height ?? 240"
    :options="options"
    :series="series"
  />
</template>
