<script setup lang="ts">
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()

const toneStyles: Record<string, string> = {
  info:    'background: var(--color-surface-2); color: var(--color-text)',
  success: 'background: var(--color-green-soft); color: var(--color-green)',
  warning: 'background: var(--color-yellow-soft); color: var(--color-yellow)',
  error:   'background: var(--color-red-soft); color: var(--color-red)',
}
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed left-0 right-0 z-50 flex flex-col items-center gap-2 pointer-events-none"
      style="bottom: calc(env(safe-area-inset-bottom) + 76px)"
    >
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts"
          :key="t.id"
          class="pointer-events-auto px-4 py-2.5 rounded-[var(--radius-md)] text-sm font-medium shadow-lg max-w-xs text-center cursor-pointer"
          :style="toneStyles[t.tone] ?? toneStyles.info"
          @click="dismiss(t.id)"
        >
          {{ t.message }}
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 200ms var(--ease-out);
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(8px) scale(0.96);
}
</style>
