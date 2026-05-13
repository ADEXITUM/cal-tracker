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
    logout: vi.fn().mockResolvedValue(undefined),
    me: vi.fn(),
    updateMe: vi.fn(),
  },
}))

vi.mock('@/api/client', () => ({
  configureClient: vi.fn(),
  ValidationError: class ValidationError extends Error {
    constructor(public errors: Record<string, string[]>) { super() }
  },
  NetworkError: class NetworkError extends Error {},
  ApiError: class ApiError extends Error {
    constructor(message: string, public status: number) { super(message) }
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
        user: { uuid: 'u1', name: 'Test', email: 'test@x.com', avatarColor: '#FF5A1F', timezone: 'UTC', hasProfile: false, role: 'user' },
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
        user: { uuid: 'u1', name: 'Test', email: 'test@x.com', avatarColor: '#FF5A1F', timezone: 'UTC', hasProfile: false, role: 'user' },
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
        user: { uuid, name: 'U', email, avatarColor: '#FF5A1F', timezone: 'UTC', hasProfile: true, role: 'user' as const },
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

  it('updateName syncs currentUser and saved account', async () => {
    vi.mocked(authApi.login).mockResolvedValue({
      data: {
        user: { uuid: 'u1', name: 'Old', email: 'x@x.com', avatarColor: '#FF5A1F', timezone: 'UTC', hasProfile: true, role: 'user' },
        token: 'tok',
      },
    })
    vi.mocked(authApi.updateMe).mockResolvedValue({
      data: {
        user: { uuid: 'u1', name: 'New', email: 'x@x.com', avatarColor: '#FF5A1F', timezone: 'UTC', hasProfile: true, role: 'user' },
      },
    })

    const auth = useAuthStore()
    await auth.login('x@x.com', 'pass', 'device')
    await auth.updateName('New')

    expect(auth.currentUser?.name).toBe('New')
    expect(auth.savedAccounts[0].name).toBe('New')
  })

  it('removes account', async () => {
    vi.mocked(authApi.login).mockResolvedValue({
      data: {
        user: { uuid: 'u1', name: 'T', email: 'x@x.com', avatarColor: '#FF5A1F', timezone: 'UTC', hasProfile: true, role: 'user' },
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
