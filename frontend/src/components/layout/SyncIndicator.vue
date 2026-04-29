<script setup lang="ts">
import { computed } from 'vue'
import { useOfflineQueue } from '@/composables/useOfflineQueue'

const { pendingCount, processing, isOnline, processQueue } = useOfflineQueue()

const visible = computed(() => pendingCount.value > 0 || !isOnline.value)

const stateColor = computed(() => {
  if (!isOnline.value) return 'var(--color-text-3)'
  if (processing.value) return 'var(--color-accent)'
  return 'var(--color-yellow)'
})

const label = computed(() => {
  if (!isOnline.value) return pendingCount.value > 0 ? `Оффлайн · ${pendingCount.value}` : 'Оффлайн'
  if (processing.value) return `Синк… ${pendingCount.value}`
  return `Очередь ${pendingCount.value}`
})

function tap() {
  if (isOnline.value) processQueue()
}
</script>

<template>
  <button
    v-if="visible"
    type="button"
    class="fixed top-3 right-3 z-40 flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium shadow-md transition-transform active:scale-95"
    style="background: var(--color-surface); border: 1px solid var(--color-border); color: var(--color-text-2)"
    @click="tap"
  >
    <span
      class="w-2 h-2 rounded-full"
      :class="processing ? 'animate-pulse' : ''"
      :style="{ background: stateColor }"
    />
    <span>{{ label }}</span>
  </button>
</template>
