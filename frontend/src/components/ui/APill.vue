<script setup lang="ts">
withDefaults(defineProps<{
  color?: 'accent' | 'green' | 'red' | 'yellow' | 'neutral'
}>(), { color: 'neutral' })
</script>

<template>
  <span
    :class="[
      'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-medium',
      {
        'bg-[var(--color-accent-soft)] text-[var(--color-accent)]': color === 'accent',
        'bg-[var(--color-green-soft)] text-[var(--color-green)]': color === 'green',
        'bg-[var(--color-red-soft)] text-[var(--color-red)]': color === 'red',
        'bg-[var(--color-yellow-soft)] text-[var(--color-yellow)]': color === 'yellow',
        'bg-[var(--color-surface-2)] text-[var(--color-text-2)]': color === 'neutral',
      },
    ]"
  >
    <slot />
  </span>
</template>
