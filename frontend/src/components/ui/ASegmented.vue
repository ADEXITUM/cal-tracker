<script setup lang="ts" generic="T extends string | number">
defineProps<{
  modelValue: T
  options: { value: T; label: string }[]
}>()
defineEmits<{ 'update:modelValue': [v: T] }>()
</script>

<template>
  <div
    class="inline-flex rounded-[var(--radius-sm)] p-0.5 gap-0.5"
    style="background: var(--color-surface-2)"
  >
    <button
      v-for="opt in options"
      :key="String(opt.value)"
      type="button"
      class="px-3 py-1.5 rounded-[calc(var(--radius-sm)-2px)] text-sm font-medium transition-colors"
      :style="{
        background: modelValue === opt.value ? 'var(--color-bg)' : 'transparent',
        color: modelValue === opt.value ? 'var(--color-text)' : 'var(--color-text-3)',
        boxShadow: modelValue === opt.value ? '0 1px 2px rgba(0,0,0,0.06)' : 'none',
      }"
      @click="$emit('update:modelValue', opt.value)"
    >
      {{ opt.label }}
    </button>
  </div>
</template>
