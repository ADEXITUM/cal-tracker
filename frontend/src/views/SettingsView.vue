<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ACard from '@/components/ui/ACard.vue'
import AConfirm from '@/components/ui/AConfirm.vue'
import { useTheme } from '@/composables/useTheme'

const router = useRouter()
const auth = useAuthStore()
const { preference: themePref, setTheme } = useTheme()

const themeOptions = [
  { value: 'auto', label: 'Авто' },
  { value: 'light', label: 'Светлая' },
  { value: 'dark', label: 'Тёмная' },
] as const

const sections = [
  { label: 'Профиль', route: '/settings/profile', hint: 'Имя, пол, дата рождения, рост' },
  { label: 'Блюда', route: '/dishes', hint: 'Свои продукты и блюда' },
  { label: 'Как это работает', route: '/settings/how-it-works', hint: 'Расход, цель, режимы — что откуда' },
] as const

const otherAccounts = computed(() =>
  auth.savedAccounts.filter(a => a.uuid !== auth.currentUser?.uuid),
)

const showLogoutConfirm = ref(false)

async function doLogout() {
  showLogoutConfirm.value = false
  await auth.logout()
  router.push({ name: 'login' })
}

async function switchTo(uuid: string) {
  if (auth.switching) return
  await auth.switchTo(uuid)
  if (!auth.currentUser) router.push({ name: 'login' })
  else router.push({ name: 'day' })
}

async function addAccount() {
  // Keep saved accounts intact, just clear in-memory session and go to login.
  auth.unloadCurrentSession()
  router.push({ name: 'login' })
}

const accountToRemove = ref<{ uuid: string; name: string; email: string } | null>(null)

function askRemoveAccount(acc: { uuid: string; name: string; email: string }) {
  accountToRemove.value = acc
}

async function confirmRemoveAccount() {
  const acc = accountToRemove.value
  accountToRemove.value = null
  if (acc) {
    await auth.removeAccount(acc.uuid)
  }
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
      <!-- Current user (tap → edit profile) -->
      <button
        v-if="auth.currentUser"
        type="button"
        class="flex items-center gap-3 px-4 py-3 rounded-[var(--radius-md)] text-left transition-colors active:scale-[0.99]"
        style="background: var(--color-surface)"
        @click="router.push('/settings/profile')"
      >
        <div
          class="w-10 h-10 rounded-full flex items-center justify-center font-medium text-base"
          style="background: #FF5A1F; color: #fff"
        >
          {{ auth.currentUser.name.slice(0, 1).toUpperCase() }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold truncate" style="color: var(--color-text)">{{ auth.currentUser.name }}</p>
          <p class="text-xs truncate" style="color: var(--color-text-3)">{{ auth.currentUser.email }}</p>
        </div>
        <span style="color: var(--color-text-3)">›</span>
      </button>

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
              class="w-8 h-8 rounded-full flex items-center justify-center font-medium text-sm flex-shrink-0"
              style="background: #FF5A1F; color: #fff"
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
              @click.stop="askRemoveAccount(acc)"
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

      <!-- Theme -->
      <div class="mt-3 flex flex-col gap-2">
        <p class="text-xs px-1" style="color: var(--color-text-3)">Оформление</p>
        <ACard>
          <div class="px-4 py-3 flex items-center justify-between gap-3">
            <p class="text-sm font-medium" style="color: var(--color-text)">Тема</p>
            <div class="flex gap-1">
              <button
                v-for="opt in themeOptions"
                :key="opt.value"
                type="button"
                class="text-xs px-2.5 py-1 rounded-[var(--radius-sm)] transition-colors"
                :style="themePref === opt.value
                  ? 'background: var(--color-accent); color: #fff'
                  : 'background: var(--color-surface-2); color: var(--color-text-2)'"
                @click="setTheme(opt.value)"
              >{{ opt.label }}</button>
            </div>
          </div>
        </ACard>
      </div>

      <div class="mt-2 flex flex-col gap-2">
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
        @click="showLogoutConfirm = true"
      >
        Выйти
      </button>

      <p class="text-xs text-center mt-4" style="color: var(--color-text-3)">
        Кал Трекер — v1.0
      </p>
    </div>

    <AConfirm
      :model-value="accountToRemove !== null"
      title="Удалить аккаунт?"
      :message="accountToRemove ? `Аккаунт «${accountToRemove.name}» (${accountToRemove.email}) будет удалён из списка сохранённых на этом устройстве.` : ''"
      confirm-label="Удалить"
      @update:model-value="(v) => { if (!v) accountToRemove = null }"
      @confirm="confirmRemoveAccount"
    />

    <AConfirm
      v-model="showLogoutConfirm"
      title="Выйти из аккаунта?"
      message="Вы выйдете из текущего аккаунта на этом устройстве."
      confirm-label="Выйти"
      @confirm="doLogout"
    />
  </div>
</template>
