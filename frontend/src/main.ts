import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { useDayStore } from '@/stores/day'
import { configureOfflineQueue } from '@/composables/useOfflineQueue'
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
})

auth.restoreFromIdb().finally(() => {
  app.mount('#app')
})
