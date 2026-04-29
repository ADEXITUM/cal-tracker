<script setup lang="ts">
import { useRouter } from 'vue-router'

defineProps<{
  title: string
  back?: boolean
  backTo?: string
}>()

const router = useRouter()

function goBack(backTo?: string) {
  if (backTo) router.push(backTo)
  else if (window.history.length > 1) router.back()
  else router.push('/day')
}
</script>

<template>
  <header
    class="sticky top-0 z-10 flex items-center gap-2 px-4 py-3"
    style="background: var(--color-bg); border-bottom: 1px solid var(--color-border)"
  >
    <button
      v-if="back"
      type="button"
      class="-ml-2 p-2 transition-transform active:scale-95"
      style="color: var(--color-text-2)"
      aria-label="Назад"
      @click="goBack(backTo)"
    >
      ←
    </button>
    <h1 class="text-base font-semibold flex-1" style="color: var(--color-text)">{{ title }}</h1>
    <slot name="right" />
  </header>
</template>
