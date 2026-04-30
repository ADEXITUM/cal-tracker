import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { useDayStore } from '@/stores/day'
import { configureOfflineQueue, processQueue } from '@/composables/useOfflineQueue'
import { useTheme } from '@/composables/useTheme'
import { useToast } from '@/composables/useToast'
import './style.css'
import App from './App.vue'

// Apply saved theme before first paint to avoid flash
useTheme()

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(router)

// Restore session from IDB before mounting
const auth = useAuthStore()

// Wire the offline queue: it needs the auth token, and should refresh
// the day store after a queued action lands on the server.
configureOfflineQueue({
  getToken: () => auth.currentToken,
  onSuccess: () => {
    const day = useDayStore()
    void day.fetch()
  },
  onTerminalFailure: (_action, _message) => {
    const { show } = useToast()
    show('Действие не удалось отправить на сервер', 'error', 5000)
  },
  onOverflow: (_dropped) => {
    const { show } = useToast()
    show('Слишком много несинхронизированных действий — старейшее удалено', 'warning', 5000)
  },
})

auth.restoreFromIdb().finally(() => {
  app.mount('#app')

  // Kick the offline queue at cold start: if the user closed the tab with
  // pending actions and re-opens online, we want to flush them right away.
  void processQueue()

  // Try to flush again whenever the tab returns to the foreground —
  // 'online' fires only on connection-state changes, so a backgrounded
  // tab that came back to focus while already online would otherwise
  // sit on stale items.
  if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') void processQueue()
    })
  }
})
