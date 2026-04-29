<script setup lang="ts">
import type { ModeCode } from '@/types/api'

defineProps<{ code: ModeCode; label: string; deltaKcal: number }>()

const config: Record<ModeCode, { bg: string; text: string; icon: string }> = {
  extreme_cut: { bg: 'var(--color-red-soft)',    text: 'var(--color-red)',    icon: '🔥' },
  cut:         { bg: 'var(--color-red-soft)',    text: 'var(--color-red)',    icon: '↘' },
  cut_lite:    { bg: 'var(--color-yellow-soft)', text: 'var(--color-yellow)', icon: '−' },
  maintenance: { bg: 'var(--color-surface-2)',   text: 'var(--color-text-2)', icon: '=' },
  light_bulk:  { bg: 'var(--color-green-soft)',  text: 'var(--color-green)',  icon: '+' },
  bulk:        { bg: 'var(--color-green-soft)',  text: 'var(--color-green)',  icon: '↗' },
}
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium"
    :style="{ background: config[code].bg, color: config[code].text }"
  >
    <span>{{ config[code].icon }}</span>
    <span>{{ label }}</span>
    <span class="opacity-70 text-xs">{{ deltaKcal > 0 ? '+' : '' }}{{ deltaKcal }} ккал</span>
  </span>
</template>
