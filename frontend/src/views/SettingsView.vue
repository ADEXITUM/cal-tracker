<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ACard from '@/components/ui/ACard.vue'

const router = useRouter()
const auth = useAuthStore()

const sections = [
  { label: 'Блюда', route: '/dishes', hint: 'Свои продукты и блюда' },
] as const

const otherAccounts = computed(() =>
  auth.savedAccounts.filter(a => a.uuid !== auth.currentUser?.uuid),
)

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}

async function switchTo(uuid: string) {
  await auth.switchTo(uuid)
  if (!auth.currentUser) router.push({ name: 'login' })
  else router.push({ name: 'day' })
}

async function addAccount() {
  // Keep saved accounts intact, just clear in-memory session and go to login.
  auth.unloadCurrentSession()
  router.push({ name: 'login' })
}

async function removeAccount(uuid: string) {
  await auth.removeAccount(uuid)
}
</script>

<template>
  <div class="flex flex-col min-h-svh" style="background: var(--color-bg)">
    <header
      class="sticky top-0 z-10 flex items-center px-4 py-3"
      style="background: var(--color-bg); border-bottom: 1px solid var(--color-border)"
    >
      <h1 class="text-base font-semibold" style="color: var(--color-text)">Настройки</h1>
    </header>

    <div class="p-4 pb-24 flex flex-col gap-3">
      <!-- Current user -->
      <div v-if="auth.currentUser" class="flex items-center gap-3 px-4 py-3 rounded-[var(--radius-md)]" style="background: var(--color-surface)">
        <div
          class="w-10 h-10 rounded-full flex items-center justify-center text-white font-medium text-base"
          :style="{ background: auth.currentUser.avatarColor }"
        >
          {{ auth.currentUser.name.slice(0, 1).toUpperCase() }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold truncate" style="color: var(--color-text)">{{ auth.currentUser.name }}</p>
          <p class="text-xs truncate" style="color: var(--color-text-3)">{{ auth.currentUser.email }}</p>
        </div>
      </div>

      <!-- Other saved accounts -->
      <div v-if="otherAccounts.length > 0" class="flex flex-col gap-2 mt-2">
        <p class="text-xs px-1" style="color: var(--color-text-3)">Аккаунты</p>
        <ACard
          v-for="acc in otherAccounts"
          :key="acc.uuid"
          class="cursor-pointer"
          @click="switchTo(acc.uuid)"
        >
          <div class="px-4 py-2.5 flex items-center gap-3">
            <div
              class="w-8 h-8 rounded-full flex items-center justify-center text-white font-medium text-sm flex-shrink-0"
              :style="{ background: acc.avatarColor }"
            >
              {{ acc.name.slice(0, 1).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium truncate" style="color: var(--color-text)">{{ acc.name }}</p>
              <p class="text-xs truncate" style="color: var(--color-text-3)">{{ acc.email }}</p>
            </div>
            <button
              type="button"
              class="text-xs p-1 -mr-1"
              style="color: var(--color-text-3)"
              aria-label="Удалить"
              @click.stop="removeAccount(acc.uuid)"
            >✕</button>
          </div>
        </ACard>
      </div>

      <button
        class="text-sm py-2.5 rounded-[var(--radius-sm)] transition-colors"
        style="color: var(--color-accent); border: 1px solid var(--color-border)"
        @click="addAccount"
      >
        + Добавить аккаунт
      </button>

      <div class="mt-3 flex flex-col gap-2">
        <ACard
          v-for="s in sections"
          :key="s.route"
          class="cursor-pointer"
          @click="router.push(s.route)"
        >
          <div class="px-4 py-3 flex items-center justify-between">
            <div>
              <p class="text-sm font-medium" style="color: var(--color-text)">{{ s.label }}</p>
              <p class="text-xs mt-0.5" style="color: var(--color-text-3)">{{ s.hint }}</p>
            </div>
            <span style="color: var(--color-text-3)">›</span>
          </div>
        </ACard>
      </div>

      <button
        class="text-sm py-3 mt-4 rounded-[var(--radius-sm)] transition-colors"
        style="color: var(--color-red); border: 1px solid var(--color-border)"
        @click="logout"
      >
        Выйти
      </button>

      <p class="text-xs text-center mt-4" style="color: var(--color-text-3)">
        Тёмная тема — Phase 5
      </p>
    </div>
  </div>
</template>
