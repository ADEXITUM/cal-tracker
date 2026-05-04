import { createApp, watch } from 'vue'
import { createPinia } from 'pinia'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { useDayStore } from '@/stores/day'
import { configureOfflineQueue, processQueue, type QueuedAction } from '@/composables/useOfflineQueue'
import { clearCachedDay } from '@/lib/dayCache'
import { useTheme } from '@/composables/useTheme'
import { useToast } from '@/composables/useToast'
import './style.css'
import App from './App.vue'

/**
 * Pull the date out of an offline action's URL so we know which day to
 * refresh after a queued write succeeds. Matches /days/{date} (PUT) and
 * /days/{date}/(meals|measurements|workouts) (POST). Returns null for
 * actions whose URL doesn't carry a date (e.g. DELETE /meals/{uuid}).
 */
function extractDateFromUrl(url: string): string | null {
  const m = url.match(/^\/days\/(\d{4}-\d{2}-\d{2})(?:\/|$)/)
  return m ? m[1] : null
}

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
  getCurrentUserUuid: () => auth.currentUser?.uuid ?? null,
  onSuccess: (action: QueuedAction) => {
    const day = useDayStore()
    const date = extractDateFromUrl(action.url)
    // If the action targeted the day the user is currently looking at,
    // refresh in place. If it targeted a different day, drop that day's
    // IDB snapshot so the next time the user navigates there we fetch
    // fresh — otherwise they'd see a stale cached version that doesn't
    // include the queued write.
    if (date && date === day.currentDate) {
      void day.fetch({ skipCache: true })
    } else if (date) {
      const uuid = auth.currentUser?.uuid
      if (uuid) void clearCachedDay(uuid, date)
    } else {
      // DELETE /meals/{uuid} etc. — no date in URL, just refresh current view.
      void day.fetch({ skipCache: true })
    }
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

  // Re-kick whenever the active account changes — the queue is filtered
  // by userUuid, so the next user's pending writes (if any) only flush
  // once they're signed in.
  watch(() => auth.currentUser?.uuid, (uuid) => {
    if (uuid) void processQueue()
  })

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
