import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { useDayStore } from '@/stores/day'
import { configureOfflineQueue } from '@/composables/useOfflineQueue'
import './style.css'
import App from './App.vue'

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
  onTerminalFailure: (_action, message) => {
    // Surface a console error for now; toast system arrives in Phase 5
    console.error('[offline-queue] permanent failure:', message)
  },
})

auth.restoreFromIdb().finally(() => {
  app.mount('#app')
})
