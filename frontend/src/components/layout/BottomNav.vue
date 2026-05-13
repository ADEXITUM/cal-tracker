<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

interface Tab {
  name: string
  label: string
  route: string
  icon: string
  disabled?: boolean
  /** Tab is rendered only when this guard returns true. */
  visible?: () => boolean
}

const allTabs: Tab[] = [
  { name: 'day', label: 'День', route: '/day', icon: '◐' },
  // Chat is an admin-only feature; plain users don't see the tab and can't
  // navigate there even if they manually enter the URL (router guard).
  { name: 'chat', label: 'Чат', route: '/chat', icon: '✦', visible: () => auth.currentUser?.role === 'admin' },
  { name: 'stats', label: 'Прогресс', route: '/stats', icon: '▦' },
  { name: 'goals', label: 'Цели', route: '/goals', icon: '◎' },
  { name: 'settings', label: 'Настройки', route: '/settings', icon: '⚙' },
]

const tabs = computed(() => allTabs.filter((t) => !t.visible || t.visible()))

const activeName = computed(() => {
  const path = route.path
  if (path.startsWith('/day')) return 'day'
  if (path.startsWith('/chat')) return 'chat'
  if (path.startsWith('/stats') || path.startsWith('/history')) return 'stats'
  if (path.startsWith('/goals')) return 'goals'
  if (path.startsWith('/settings') || path.startsWith('/dishes')) return 'settings'
  return ''
})

function go(tab: Tab) {
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
      :data-testid="`nav-${tab.name}`"
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
