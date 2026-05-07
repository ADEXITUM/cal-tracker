<script setup lang="ts">
import AButton from './AButton.vue'

withDefaults(defineProps<{
  modelValue: boolean
  title?: string
  message?: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'danger' | 'primary'
}>(), {
  title: 'Подтвердите действие',
  message: '',
  confirmLabel: 'Удалить',
  cancelLabel: 'Отмена',
  variant: 'danger',
})

const emit = defineEmits<{
  'update:modelValue': [v: boolean]
  confirm: []
  cancel: []
}>()

function close() {
  emit('update:modelValue', false)
  emit('cancel')
}

function onConfirm() {
  emit('update:modelValue', false)
  emit('confirm')
}
</script>

<template>
  <Teleport to="body">
    <Transition name="backdrop">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-40"
        style="background: rgba(0,0,0,0.5)"
        @click="close"
      />
    </Transition>
    <Transition name="dialog">
      <div
        v-if="modelValue"
        role="dialog"
        aria-modal="true"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none"
      >
        <div
          class="w-full max-w-[360px] pointer-events-auto"
          style="background: var(--color-surface); border-radius: var(--radius-md); border: 1px solid var(--color-border)"
          @click.stop
        >
          <div class="px-5 pt-5 pb-3">
            <p class="text-base font-semibold" style="color: var(--color-text)">{{ title }}</p>
            <p v-if="message" class="text-sm mt-2" style="color: var(--color-text-2)">{{ message }}</p>
          </div>
          <div class="flex gap-2 px-5 pb-5">
            <AButton variant="secondary" size="md" class="flex-1" @click="close">
              {{ cancelLabel }}
            </AButton>
            <AButton :variant="variant" size="md" class="flex-1" @click="onConfirm">
              {{ confirmLabel }}
            </AButton>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.backdrop-enter-active, .backdrop-leave-active { transition: opacity 200ms; }
.backdrop-enter-from, .backdrop-leave-to { opacity: 0; }

.dialog-enter-active { transition: opacity 200ms, transform 200ms cubic-bezier(0.32, 0.72, 0, 1); }
.dialog-leave-active { transition: opacity 150ms, transform 150ms ease-in; }
.dialog-enter-from, .dialog-leave-to { opacity: 0; transform: scale(0.96); }
</style>
