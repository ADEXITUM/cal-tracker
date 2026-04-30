<script setup lang="ts">
defineProps<{
  label?: string
  error?: string
  modelValue?: string | number
  type?: string
  placeholder?: string
  autocomplete?: string
  inputmode?: 'none' | 'text' | 'decimal' | 'numeric' | 'tel' | 'search' | 'email' | 'url'
  disabled?: boolean
}>()

defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <div class="flex flex-col gap-1">
    <label v-if="label" class="text-sm font-medium text-[var(--color-text)]">{{ label }}</label>
    <input
      :value="modelValue"
      :type="type ?? 'text'"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      :inputmode="inputmode"
      :disabled="disabled"
      :class="[
        'w-full rounded-[var(--radius-sm)] border px-3 py-2.5 text-base bg-[var(--color-surface)] text-[var(--color-text)] placeholder:text-[var(--color-text-3)] transition-colors outline-none',
        error
          ? 'border-[var(--color-red)] focus:border-[var(--color-red)]'
          : 'border-[var(--color-border)] focus:border-[var(--color-accent)]',
        'disabled:opacity-50 disabled:cursor-not-allowed',
      ]"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <p v-if="error" class="text-sm text-[var(--color-red)]">{{ error }}</p>
  </div>
</template>
