import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'

// Mock idb-keyval
vi.mock('idb-keyval', () => ({
  get: vi.fn().mockResolvedValue(null),
  set: vi.fn().mockResolvedValue(undefined),
  del: vi.fn().mockResolvedValue(undefined),
  keys: vi.fn().mockResolvedValue([]),
}))

// Mock API
vi.mock('@/api/auth', () => ({
  authApi: {
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn().mockResolvedValue(undefined),
    me: vi.fn(),
  },
}))

vi.mock('@/api/client', () => ({
  configureClient: vi.fn(),
  ValidationError: class ValidationError extends Error {
    constructor(public errors: Record<string, string[]>) { super() }
  },
}))

import { authApi } from '@/api/auth'

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('starts unauthenticated', () => {
    const auth = useAuthStore()
    expect(auth.isAuthenticated).toBe(false)
    expect(auth.currentUser).toBeNull()
  })

  it('sets user and token on login', async () => {
    vi.mocked(authApi.login).mockResolvedValue({
      data: {
        user: { uuid: 'u1', name: 'Test', email: 'test@x.com', avatarColor: '#FF5A1F', timezone: 'UTC', hasProfile: false },
        token: 'tok123',
      },
    })

    const auth = useAuthStore()
    await auth.login('test@x.com', 'pass', 'device')

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.currentUser?.email).toBe('test@x.com')
    expect(auth.currentToken).toBe('tok123')
    expect(auth.savedAccounts).toHaveLength(1)
  })

  it('clears user on logout', async () => {
    vi.mocked(authApi.login).mockResolvedValue({
      data: {
        user: { uuid: 'u1', name: 'Test', email: 'test@x.com', avatarColor: '#FF5A1F', timezone: 'UTC', hasProfile: false },
        token: 'tok123',
      },
    })

    const auth = useAuthStore()
    await auth.login('test@x.com', 'pass', 'device')
    await auth.logout()

    expect(auth.isAuthenticated).toBe(false)
    expect(auth.currentUser).toBeNull()
    expect(auth.currentToken).toBeNull()
  })

  it('saves multiple accounts', async () => {
    const makeUser = (uuid: string, email: string) => ({
      data: {
        user: { uuid, name: 'U', email, avatarColor: '#FF5A1F', timezone: 'UTC', hasProfile: true },
        token: `tok-${uuid}`,
      },
    })

    vi.mocked(authApi.login)
      .mockResolvedValueOnce(makeUser('u1', 'a@x.com'))
      .mockResolvedValueOnce(makeUser('u2', 'b@x.com'))

    const auth = useAuthStore()
    await auth.login('a@x.com', 'pass', 'device')
    await auth.login('b@x.com', 'pass', 'device')

    expect(auth.savedAccounts).toHaveLength(2)
  })

  it('removes account', async () => {
    vi.mocked(authApi.login).mockResolvedValue({
      data: {
        user: { uuid: 'u1', name: 'T', email: 'x@x.com', avatarColor: '#FF5A1F', timezone: 'UTC', hasProfile: true },
        token: 'tok',
      },
    })

    const auth = useAuthStore()
    await auth.login('x@x.com', 'pass', 'device')
    await auth.removeAccount('u1')

    expect(auth.savedAccounts).toHaveLength(0)
    expect(auth.isAuthenticated).toBe(false)
  })
})
