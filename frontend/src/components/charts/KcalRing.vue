<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'

const props = withDefaults(defineProps<{
  current: number
  goal: number
  size?: number
}>(), { size: 220 })

const STROKE = 12
const radius = computed(() => (props.size - STROKE) / 2)
const circumference = computed(() => 2 * Math.PI * radius.value)

const displayValue = ref(0)
const dashOffset = computed(() => {
  const pct = Math.min(displayValue.value / (props.goal || 1), 1)
  return circumference.value * (1 - pct)
})

const over = computed(() => props.goal > 0 && props.current > props.goal + 50)

// Animate on mount and when value changes
function animateTo(target: number) {
  const start = displayValue.value
  const duration = 800
  const startTime = performance.now()
  const step = (now: number) => {
    const t = Math.min((now - startTime) / duration, 1)
    const ease = 1 - Math.pow(1 - t, 3)
    displayValue.value = Math.round(start + (target - start) * ease)
    if (t < 1) requestAnimationFrame(step)
  }
  requestAnimationFrame(step)
}

onMounted(() => animateTo(props.current))
watch(() => props.current, (v) => animateTo(v))
</script>

<template>
  <div class="relative inline-flex items-center justify-center" :style="{ width: `${size}px`, height: `${size}px` }">
    <svg :width="size" :height="size" style="transform: rotate(-90deg)">
      <!-- Track -->
      <circle
        :cx="size / 2" :cy="size / 2" :r="radius"
        fill="none" stroke="var(--color-surface-3)" :stroke-width="STROKE"
      />
      <!-- Progress -->
      <circle
        :cx="size / 2" :cy="size / 2" :r="radius"
        fill="none"
        :stroke="over ? 'var(--color-red)' : 'var(--color-accent)'"
        :stroke-width="STROKE"
        stroke-linecap="round"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="dashOffset"
        style="transition: stroke-dashoffset 50ms linear"
      />
    </svg>
    <div class="absolute flex flex-col items-center">
      <span class="font-mono font-light" :style="{ fontSize: size > 240 ? '48px' : '36px', color: 'var(--color-text)', lineHeight: 1 }">
        {{ displayValue.toLocaleString('ru') }}
      </span>
      <span class="text-sm mt-1" style="color: var(--color-text-3)">из {{ goal.toLocaleString('ru') }} ккал</span>
    </div>
  </div>
</template>
