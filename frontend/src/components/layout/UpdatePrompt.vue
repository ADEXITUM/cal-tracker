<script setup lang="ts">
import { useRegisterSW } from 'virtual:pwa-register/vue'

// Auto-checks for SW updates every hour while the tab is open.
const { needRefresh, updateServiceWorker } = useRegisterSW({
  immediate: true,
  onRegisteredSW(_url, registration) {
    if (!registration) return
    setInterval(() => {
      void registration.update()
    }, 60 * 60 * 1000)
  },
})

function applyUpdate() {
  void updateServiceWorker(true)
}

function dismiss() {
  needRefresh.value = false
}
</script>

<template>
  <Transition name="update">
    <div
      v-if="needRefresh"
      class="fixed left-3 right-3 z-50 rounded-[var(--radius-md)] shadow-xl flex items-center gap-3 px-4 py-3"
      style="bottom: calc(env(safe-area-inset-bottom) + 5rem); background: var(--color-surface); border: 1px solid var(--color-border)"
    >
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium" style="color: var(--color-text)">Доступна новая версия</p>
        <p class="text-xs" style="color: var(--color-text-3)">Обновите, чтобы получить последние улучшения.</p>
      </div>
      <button
        type="button"
        class="text-xs px-3 py-1.5 rounded-[var(--radius-sm)] font-medium flex-shrink-0"
        style="background: var(--color-accent); color: #fff"
        @click="applyUpdate"
      >Обновить</button>
      <button
        type="button"
        class="text-xs px-2 py-1 flex-shrink-0"
        style="color: var(--color-text-3)"
        aria-label="Закрыть"
        @click="dismiss"
      >✕</button>
    </div>
  </Transition>
</template>

<style scoped>
.update-enter-active,
.update-leave-active {
  transition: opacity 200ms ease, transform 200ms ease;
}
.update-enter-from,
.update-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
