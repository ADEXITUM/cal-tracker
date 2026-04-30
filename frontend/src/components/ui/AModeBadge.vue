<script setup lang="ts">
import type { ModeCode } from '@/types/api'

defineProps<{ code: ModeCode; label: string; deltaKcal: number; clickable?: boolean }>()
defineEmits<{ click: [] }>()

const config: Record<ModeCode, { bg: string; text: string; icon: string }> = {
  on_target: { bg: 'var(--color-accent-soft)', text: 'var(--color-accent)', icon: '✓' },
  over:      { bg: 'var(--color-yellow-soft)', text: 'var(--color-yellow)', icon: '↑' },
  far_over:  { bg: 'var(--color-red-soft)',    text: 'var(--color-red)',    icon: '↑↑' },
  under:     { bg: 'var(--color-yellow-soft)', text: 'var(--color-yellow)', icon: '↓' },
  far_under: { bg: 'var(--color-red-soft)',    text: 'var(--color-red)',    icon: '↓↓' },
}
</script>

<template>
  <component
    :is="clickable ? 'button' : 'span'"
    :type="clickable ? 'button' : undefined"
    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium transition-transform"
    :class="clickable ? 'cursor-pointer active:scale-95' : ''"
    :style="{ background: config[code].bg, color: config[code].text }"
    @click="clickable ? $emit('click') : undefined"
  >
    <span>{{ config[code].icon }}</span>
    <span>{{ label }}</span>
    <span class="opacity-70 text-xs">{{ deltaKcal > 0 ? '+' : '' }}{{ deltaKcal }} ккал</span>
  </component>
</template>
