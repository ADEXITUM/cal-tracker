import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { get as idbGet, set as idbSet } from 'idb-keyval'
import { configureClient } from '@/api/client'
import { authApi } from '@/api/auth'
import type { SavedAccount, User } from '@/types/api'

const IDB_ACCOUNTS_KEY = 'saved_accounts'

export const useAuthStore = defineStore('auth', () => {
  const currentUser = ref<User | null>(null)
  const currentToken = ref<string | null>(null)
  const savedAccounts = ref<SavedAccount[]>([])

  const isAuthenticated = computed(() => currentUser.value !== null)

  function _wire() {
    configureClient({
      getToken: () => currentToken.value,
      onUnauthorized: () => logout(),
    })
  }

  async function restoreFromIdb(): Promise<void> {
    _wire()
    const accounts = await idbGet<SavedAccount[]>(IDB_ACCOUNTS_KEY).catch(() => null)
    if (accounts?.length) {
      savedAccounts.value = accounts
      const last = accounts.sort((a, b) => b.lastUsedAt - a.lastUsedAt)[0]
      currentToken.value = last.token
      try {
        const res = await authApi.me()
        currentUser.value = res.data.user
        await _persistAccounts()
      } catch {
        currentToken.value = null
      }
    }
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
    currentUser.value = null
    currentToken.value = null
    if (user) {
      savedAccounts.value = savedAccounts.value.filter(a => a.uuid !== user.uuid)
      await _persistAccounts()
    }
    try { await authApi.logout() } catch { /* ignore — token already cleared */ }
  }

  async function switchTo(uuid: string): Promise<void> {
    const account = savedAccounts.value.find(a => a.uuid === uuid)
    if (!account) return
    currentToken.value = account.token
    try {
      const res = await authApi.me()
      currentUser.value = res.data.user
      account.lastUsedAt = Date.now()
      await _persistAccounts()
    } catch {
      await removeAccount(uuid)
    }
  }

  async function removeAccount(uuid: string): Promise<void> {
    savedAccounts.value = savedAccounts.value.filter(a => a.uuid !== uuid)
    await _persistAccounts()
    if (currentUser.value?.uuid === uuid) {
      currentUser.value = null
      currentToken.value = null
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
    await _persistAccounts()
  }

  async function _persistAccounts(): Promise<void> {
    try {
      await idbSet(IDB_ACCOUNTS_KEY, savedAccounts.value)
    } catch {
      // IDB unavailable (private mode quota, etc.) — state is still in memory
    }
  }

  _wire()

  return {
    currentUser,
    currentToken,
    savedAccounts,
    isAuthenticated,
    restoreFromIdb,
    login,
    register,
    logout,
    switchTo,
    removeAccount,
  }
})
