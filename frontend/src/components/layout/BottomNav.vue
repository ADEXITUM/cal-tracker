<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const tabs = [
  { name: 'day', label: 'День', route: '/day', icon: '◐' },
  { name: 'stats', label: 'Статистика', route: '/stats', icon: '▦', disabled: true },
  { name: 'dishes', label: 'Блюда', route: '/dishes', icon: '◇' },
  { name: 'settings', label: 'Настройки', route: '/settings', icon: '⚙', disabled: true },
] as const

const activeName = computed(() => {
  const path = route.path
  if (path.startsWith('/day')) return 'day'
  if (path.startsWith('/dishes')) return 'dishes'
  if (path.startsWith('/stats')) return 'stats'
  if (path.startsWith('/settings')) return 'settings'
  return ''
})

function go(tab: typeof tabs[number]) {
  if (tab.disabled) return
  if (activeName.value === tab.name) return
  router.push(tab.route)
}
</script>

<template>
  <nav
    class="fixed bottom-0 inset-x-0 z-20 flex items-stretch justify-around"
    style="background: var(--color-surface); border-top: 1px solid var(--color-border); padding-bottom: env(safe-area-inset-bottom)"
  >
    <button
      v-for="tab in tabs"
      :key="tab.name"
      type="button"
      class="flex flex-1 flex-col items-center justify-center gap-0.5 py-2 transition-colors"
      :class="{ 'opacity-40 cursor-not-allowed': tab.disabled }"
      :style="{
        color: activeName === tab.name ? 'var(--color-accent)' : 'var(--color-text-3)',
      }"
      @click="go(tab)"
    >
      <span class="text-xl leading-none">{{ tab.icon }}</span>
      <span class="text-[11px] font-medium">{{ tab.label }}</span>
    </button>
  </nav>
</template>
