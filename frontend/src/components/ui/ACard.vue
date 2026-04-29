<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'default' | 'tinted' | 'accent' | 'outlined'
  accentSide?: boolean
}>(), {
  variant: 'default',
  accentSide: false,
})
</script>

<template>
  <div
    :class="[
      'rounded-[var(--radius-md)] overflow-hidden',
      {
        'bg-[var(--color-surface)] border border-[var(--color-border)]': variant === 'default',
        'bg-[var(--color-surface-2)]': variant === 'tinted',
        'bg-[var(--color-accent-tint)] border border-[var(--color-accent-soft)]': variant === 'accent',
        'border border-[var(--color-border)]': variant === 'outlined',
        'border-l-4 border-l-[var(--color-accent)]': accentSide,
      },
    ]"
  >
    <slot />
  </div>
</template>
