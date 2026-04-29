<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  disabled?: boolean
  type?: 'button' | 'submit' | 'reset'
}>(), {
  variant: 'primary',
  size: 'md',
  loading: false,
  disabled: false,
  type: 'button',
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center font-medium transition-transform active:scale-[0.97] focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 disabled:cursor-not-allowed',
      {
        'bg-[var(--color-accent)] text-white focus-visible:outline-[var(--color-accent)]': variant === 'primary',
        'bg-[var(--color-surface-2)] text-[var(--color-text)] border border-[var(--color-border)]': variant === 'secondary',
        'text-[var(--color-text-2)] hover:text-[var(--color-text)] hover:bg-[var(--color-surface-2)]': variant === 'ghost',
        'bg-[var(--color-red)] text-white': variant === 'danger',
      },
      {
        'text-sm px-3 py-1.5 rounded-[var(--radius-sm)]': size === 'sm',
        'text-base px-4 py-2.5 rounded-[var(--radius-sm)]': size === 'md',
        'text-lg px-6 py-3 rounded-[var(--radius-md)]': size === 'lg',
      },
    ]"
  >
    <span v-if="loading" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
    <slot />
  </button>
</template>
