<script setup lang="ts">
import { ref } from 'vue'

const emit = defineEmits<{ add: [type: 'meal' | 'measurement' | 'workout'] }>()
const open = ref(false)

function toggle() { open.value = !open.value }
function pick(type: 'meal' | 'measurement' | 'workout') {
  open.value = false
  emit('add', type)
}
</script>

<template>
  <div class="fixed right-4 z-30 flex flex-col items-end gap-2" style="bottom: calc(env(safe-area-inset-bottom) + 4.5rem)">
    <Transition name="fab-menu">
      <div v-if="open" class="flex flex-col items-end gap-2 mb-1">
        <button
          v-for="item in [
            { type: 'meal' as const, label: 'Приём пищи' },
            { type: 'measurement' as const, label: 'Замер' },
            { type: 'workout' as const, label: 'Тренировка' },
          ]"
          :key="item.type"
          class="flex items-center gap-2 px-4 py-2 rounded-full shadow-lg text-sm font-medium"
          style="background: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border)"
          @click="pick(item.type)"
        >
          {{ item.label }}
        </button>
      </div>
    </Transition>

    <button
      class="w-14 h-14 rounded-full shadow-xl flex items-center justify-center text-2xl text-white transition-transform active:scale-95"
      style="background: var(--color-accent)"
      :style="{ transform: open ? 'rotate(45deg)' : 'rotate(0deg)', transition: 'transform 250ms ease' }"
      @click="toggle"
    >
      +
    </button>
  </div>

  <!-- Backdrop to close -->
  <div v-if="open" class="fixed inset-0 z-20" @click="open = false" />
</template>

<style scoped>
.fab-menu-enter-active { transition: opacity 200ms, transform 200ms; }
.fab-menu-leave-active { transition: opacity 150ms, transform 150ms; }
.fab-menu-enter-from, .fab-menu-leave-to { opacity: 0; transform: translateY(8px); }
</style>
