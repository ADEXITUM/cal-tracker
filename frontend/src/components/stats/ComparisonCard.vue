<script setup lang="ts">
import { computed } from 'vue'
import ACard from '@/components/ui/ACard.vue'

const props = defineProps<{
  label: string
  start: number | null
  end: number | null
  unit?: string
  good?: 'down' | 'up' | null  // which direction is "improvement"
}>()

const delta = computed(() => {
  if (props.start === null || props.end === null) return null
  return Math.round((props.end - props.start) * 10) / 10
})

const deltaColor = computed(() => {
  if (delta.value === null || props.good === null || props.good === undefined) return 'var(--color-text-3)'
  if (delta.value === 0) return 'var(--color-text-3)'
  const isImprovement = (props.good === 'down' && delta.value < 0) || (props.good === 'up' && delta.value > 0)
  return isImprovement ? 'var(--color-green)' : 'var(--color-red)'
})
</script>

<template>
  <ACard>
    <div class="px-4 py-3">
      <p class="text-xs mb-2" style="color: var(--color-text-3)">{{ label }}</p>
      <div v-if="start !== null && end !== null" class="flex items-center gap-3">
        <div class="text-center">
          <p class="text-xs" style="color: var(--color-text-3)">было</p>
          <p class="font-mono text-lg font-light" style="color: var(--color-text-2)">{{ start }}{{ unit ? ` ${unit}` : '' }}</p>
        </div>
        <span class="text-base" style="color: var(--color-text-3)">→</span>
        <div class="text-center">
          <p class="text-xs" style="color: var(--color-text-3)">стало</p>
          <p class="font-mono text-lg" style="color: var(--color-text)">{{ end }}{{ unit ? ` ${unit}` : '' }}</p>
        </div>
        <div class="ml-auto text-right">
          <p class="text-xs" style="color: var(--color-text-3)">Δ</p>
          <p class="font-mono text-base" :style="{ color: deltaColor }">
            {{ delta !== null && delta > 0 ? '+' : '' }}{{ delta }}{{ unit ? ` ${unit}` : '' }}
          </p>
        </div>
      </div>
      <p v-else class="text-sm" style="color: var(--color-text-3)">Нет данных</p>
    </div>
  </ACard>
</template>
