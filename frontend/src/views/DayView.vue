<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AButton from '@/components/ui/AButton.vue'
import ACard from '@/components/ui/ACard.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const date = computed(() => {
  const d = route.params.date as string
  return d || new Date().toISOString().slice(0, 10)
})

const displayDate = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10)
  if (date.value === today) return 'Сегодня'
  if (date.value === yesterday) return 'Вчера'
  return new Date(date.value).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' })
})

async function handleLogout() {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex flex-col min-h-svh" style="background: var(--color-bg)">
    <!-- Header -->
    <header class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--color-border)">
      <h1 class="text-lg font-semibold" style="color: var(--color-text)">{{ displayDate }}</h1>
      <AButton variant="ghost" size="sm" @click="handleLogout">Выйти</AButton>
    </header>

    <!-- Content stubs -->
    <div class="flex flex-col gap-3 p-4">
      <!-- Kcal ring placeholder -->
      <ACard>
        <div class="flex items-center justify-center py-12" style="color: var(--color-text-3)">
          <p class="font-mono text-4xl font-light">— / —</p>
        </div>
      </ACard>

      <!-- Macros placeholder -->
      <div class="grid grid-cols-3 gap-3">
        <ACard v-for="macro in ['Белки', 'Жиры', 'Углеводы']" :key="macro">
          <div class="px-3 py-4 text-center">
            <p class="text-xs" style="color: var(--color-text-3)">{{ macro }}</p>
            <p class="font-mono text-xl font-light" style="color: var(--color-text)">—</p>
          </div>
        </ACard>
      </div>

      <!-- Meals placeholder -->
      <ACard>
        <div class="p-4">
          <p class="text-sm font-medium mb-2" style="color: var(--color-text)">Приёмы пищи</p>
          <p class="text-sm" style="color: var(--color-text-3)">Нет записей</p>
        </div>
      </ACard>

      <!-- Weight placeholder -->
      <ACard>
        <div class="p-4">
          <p class="text-sm font-medium mb-2" style="color: var(--color-text)">Замеры</p>
          <p class="text-sm" style="color: var(--color-text-3)">Нет замеров</p>
        </div>
      </ACard>
    </div>
  </div>
</template>
