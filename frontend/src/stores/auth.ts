import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { configureClient } from '@/api/client'
import { authApi } from '@/api/auth'
import type { SavedAccount, User } from '@/types/api'

/**
 * Wipe all per-user Pinia state. Called on logout / account switch /
 * unload — otherwise stale meals, dishes and goals from the previous user
 * leak into the next session's UI until the network reply arrives.
 *
 * Stores are imported lazily because they import auth themselves
 * (circular).
 */
async function resetUserStores(): Promise<void> {
  const [{ useDayStore }, { useDishesStore }, { useGoalsStore }] = await Promise.all([
    import('@/stores/day'),
    import('@/stores/dishes'),
    import('@/stores/goals'),
  ])
  useDayStore().reset()
  useDishesStore().reset()
  useGoalsStore().reset()
}

const LS_ACCOUNTS_KEY = 'dt_saved_accounts'

function lsGet<T>(key: string): T | null {
  try {
    const raw = localStorage.getItem(key)
    return raw ? (JSON.parse(raw) as T) : null
  } catch {
    return null
  }
}

function lsSet(key: string, value: unknown): void {
  try {
    localStorage.setItem(key, JSON.stringify(value))
  } catch {
    // private mode / quota
  }
}

export const useAuthStore = defineStore('auth', () => {
  const currentUser = ref<User | null>(null)
  const currentToken = ref<string | null>(null)
  const savedAccounts = ref<SavedAccount[]>([])
  const isInitialized = ref(false)

  const isAuthenticated = computed(() => currentUser.value !== null)

  function _wire() {
    configureClient({
      getToken: () => currentToken.value,
      onUnauthorized: () => logout(),
    })
  }

  async function restoreFromIdb(): Promise<void> {
    _wire()
    const accounts = lsGet<SavedAccount[]>(LS_ACCOUNTS_KEY)
    if (accounts?.length) {
      savedAccounts.value = accounts
      const last = [...accounts].sort((a, b) => b.lastUsedAt - a.lastUsedAt)[0]
      currentToken.value = last.token
      try {
        const res = await authApi.me()
        currentUser.value = res.data.user
      } catch {
        currentToken.value = null
      }
    }
    isInitialized.value = true
  }

  async function login(email: string, password: string, deviceName: string): Promise<void> {
    const res = await authApi.login({ email, password, deviceName })
    await _setSession(res.data.user, res.data.token)
  }

  async function register(name: string, email: string, password: string, deviceName: string): Promise<void> {
    const res = await authApi.register({ name, email, password, deviceName })
    await _setSession(res.data.user, res.data.token)
  }

  async function logout(): Promise<void> {
    const user = currentUser.value
    const token = currentToken.value
    currentUser.value = null
    currentToken.value = null
    await resetUserStores()
    if (user) {
      savedAccounts.value = savedAccounts.value.filter(a => a.uuid !== user.uuid)
      _persistAccounts()
    }
    // Only hit the server if we actually had a token; otherwise the 401
    // response would re-trigger onUnauthorized → logout() recursively.
    if (token) {
      try { await authApi.logout() } catch { /* ignore */ }
    }
  }

  async function switchTo(uuid: string): Promise<void> {
    const account = savedAccounts.value.find(a => a.uuid === uuid)
    if (!account) return
    // Wipe previous user's state BEFORE swapping the token so that views
    // mounted after the switch don't render the previous user's data.
    await resetUserStores()
    currentUser.value = null
    currentToken.value = account.token
    try {
      const res = await authApi.me()
      currentUser.value = res.data.user
      account.lastUsedAt = Date.now()
      _persistAccounts()
    } catch {
      await removeAccount(uuid)
    }
  }

  /**
   * Unload the active session without removing it from saved accounts.
   * Use when adding another account: the user goes to /login but their
   * existing saved accounts (including this one) stay in IDB.
   */
  function unloadCurrentSession(): void {
    currentUser.value = null
    currentToken.value = null
    void resetUserStores()
  }

  async function removeAccount(uuid: string): Promise<void> {
    savedAccounts.value = savedAccounts.value.filter(a => a.uuid !== uuid)
    _persistAccounts()
    if (currentUser.value?.uuid === uuid) {
      currentUser.value = null
      currentToken.value = null
      await resetUserStores()
    }
  }

  async function _setSession(user: User, token: string): Promise<void> {
    currentUser.value = user
    currentToken.value = token
    const existing = savedAccounts.value.findIndex(a => a.uuid === user.uuid)
    const account: SavedAccount = {
      uuid: user.uuid,
      email: user.email,
      name: user.name,
      avatarColor: user.avatarColor,
      token,
      lastUsedAt: Date.now(),
    }
    if (existing >= 0) savedAccounts.value[existing] = account
    else savedAccounts.value.push(account)
    _persistAccounts()
  }

  function _persistAccounts(): void {
    lsSet(LS_ACCOUNTS_KEY, savedAccounts.value)
  }

  async function updateName(name: string): Promise<void> {
    const res = await authApi.updateMe({ name })
    if (currentUser.value) currentUser.value = { ...currentUser.value, ...res.data.user }
    const idx = savedAccounts.value.findIndex(a => a.uuid === res.data.user.uuid)
    if (idx >= 0) {
      savedAccounts.value[idx] = { ...savedAccounts.value[idx], name: res.data.user.name }
      _persistAccounts()
    }
  }

  _wire()

  return {
    currentUser,
    currentToken,
    savedAccounts,
    isAuthenticated,
    isInitialized,
    restoreFromIdb,
    login,
    register,
    logout,
    switchTo,
    removeAccount,
    unloadCurrentSession,
    updateName,
  }
})
