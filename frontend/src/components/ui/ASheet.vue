<script setup lang="ts">
const props = defineProps<{ modelValue: boolean; title?: string }>()
const emit = defineEmits<{ 'update:modelValue': [v: boolean] }>()

function close() { emit('update:modelValue', false) }

// Swipe down to close
let startY = 0
function onTouchStart(e: TouchEvent) { startY = e.touches[0].clientY }
function onTouchEnd(e: TouchEvent) {
  if (e.changedTouches[0].clientY - startY > 100) close()
}
</script>

<template>
  <Teleport to="body">
    <Transition name="backdrop">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-40"
        style="background: rgba(0,0,0,0.4)"
        @click="close"
      />
    </Transition>
    <Transition name="sheet">
      <div
        v-if="modelValue"
        ref="sheet"
        role="dialog"
        aria-modal="true"
        class="fixed bottom-0 left-0 right-0 z-50 max-w-[480px] mx-auto"
        style="background: var(--color-surface); border-radius: var(--radius-xl) var(--radius-xl) 0 0; max-height: 90svh; overflow-y: auto"
        @touchstart="onTouchStart"
        @touchend="onTouchEnd"
      >
        <div class="flex justify-center pt-3 pb-1">
          <div class="w-8 h-1 rounded-full" style="background: var(--color-surface-3)" />
        </div>
        <div v-if="title" class="px-4 pb-3 text-lg font-semibold" style="color: var(--color-text)">{{ title }}</div>
        <div class="px-4 pb-8">
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.backdrop-enter-active, .backdrop-leave-active { transition: opacity 250ms; }
.backdrop-enter-from, .backdrop-leave-to { opacity: 0; }

.sheet-enter-active { transition: transform 320ms cubic-bezier(0.32, 0.72, 0, 1); }
.sheet-leave-active { transition: transform 250ms ease-in; }
.sheet-enter-from, .sheet-leave-to { transform: translateY(100%); }
</style>
