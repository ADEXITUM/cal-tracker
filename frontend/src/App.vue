<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import BottomNav from '@/components/layout/BottomNav.vue'
import SyncIndicator from '@/components/layout/SyncIndicator.vue'
import UpdatePrompt from '@/components/layout/UpdatePrompt.vue'
import ToastContainer from '@/components/ui/ToastContainer.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const showNav = computed(() => auth.isAuthenticated && route.meta.hideNav !== true)
</script>

<template>
  <RouterView v-slot="{ Component, route: r }">
    <Transition name="page" mode="out-in">
      <!-- key by route name (not path) so navigating between dates within
           /day/:date doesn't remount DayView and re-fetch from scratch -->
      <component :is="Component" :key="r.name as string" />
    </Transition>
  </RouterView>
  <SyncIndicator v-if="auth.isAuthenticated" />
  <BottomNav v-if="showNav" />
  <UpdatePrompt />
  <ToastContainer />

  <!-- Account-switch overlay. Blocks every interaction so a user on slow
       network can't fire writes while the token has been swapped but the
       new /auth/me is still in flight. -->
  <Transition name="overlay">
    <div
      v-if="auth.switching"
      class="fixed inset-0 z-[100] flex items-center justify-center"
      style="background: rgba(0,0,0,0.45); backdrop-filter: blur(2px)"
      aria-busy="true"
    >
      <div
        class="flex items-center gap-3 px-5 py-4 rounded-[var(--radius-md)]"
        style="background: var(--color-surface); border: 1px solid var(--color-border)"
      >
        <span class="switch-spinner" />
        <p class="text-sm" style="color: var(--color-text)">Переключение аккаунта…</p>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.overlay-enter-active, .overlay-leave-active { transition: opacity 180ms; }
.overlay-enter-from, .overlay-leave-to { opacity: 0; }

.switch-spinner {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid var(--color-border);
  border-top-color: var(--color-accent);
  animation: spin 700ms linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
