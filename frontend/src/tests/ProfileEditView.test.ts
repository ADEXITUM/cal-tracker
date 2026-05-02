import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('idb-keyval', () => ({
  get: vi.fn().mockResolvedValue(null),
  set: vi.fn().mockResolvedValue(undefined),
  del: vi.fn().mockResolvedValue(undefined),
  keys: vi.fn().mockResolvedValue([]),
}))

vi.mock('@/api/profile', () => ({
  profileApi: {
    get: vi.fn(),
    upsert: vi.fn(),
  },
}))

vi.mock('@/api/auth', () => ({
  authApi: {
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
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
    constructor(message: string, public status: number) { super(message); this.name = 'ApiError' }
  },
}))

import { profileApi } from '@/api/profile'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import ProfileEditView from '@/views/ProfileEditView.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/settings/profile', name: 'profile', component: ProfileEditView },
      { path: '/profile/setup', name: 'profile-setup', component: { template: '<div />' } },
    ],
  })
}

async function mountView() {
  const router = makeRouter()
  await router.push('/settings/profile')
  await router.isReady()
  const wrapper = mount(ProfileEditView, {
    global: { plugins: [router] },
  })
  await flushPromises()
  return { wrapper, router }
}

describe('ProfileEditView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    const auth = useAuthStore()
    auth.currentUser = {
      uuid: 'u1', name: 'Old Name', email: 'x@x.com',
      avatarColor: '#FF5A1F', timezone: 'UTC', hasProfile: true,
    }
  })

  it('hydrates form from existing profile', async () => {
    vi.mocked(profileApi.get).mockResolvedValue({
      data: { gender: 'male', birthDate: '1990-05-15', heightCm: 180, tdeeKcal: null },
    })

    const { wrapper } = await mountView()

    const inputs = wrapper.findAll('input')
    // name, birthDate, heightCm
    expect((inputs[0].element as HTMLInputElement).value).toBe('Old Name')
    expect((inputs[1].element as HTMLInputElement).value).toBe('15/05/1990')
    expect((inputs[2].element as HTMLInputElement).value).toBe('180')
  })

  it('redirects to setup when profile is missing', async () => {
    vi.mocked(profileApi.get).mockRejectedValue(new ApiError('Not found', 404))

    const { router } = await mountView()
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('profile-setup')
  })

  it('saves changed name and profile fields', async () => {
    vi.mocked(profileApi.get).mockResolvedValue({
      data: { gender: 'male', birthDate: '1990-05-15', heightCm: 180, tdeeKcal: null },
    })
    vi.mocked(profileApi.upsert).mockResolvedValue({
      data: { gender: 'female', birthDate: '1990-05-15', heightCm: 175, tdeeKcal: null },
    })
    vi.mocked(authApi.updateMe).mockResolvedValue({
      data: {
        user: { uuid: 'u1', name: 'New Name', email: 'x@x.com', avatarColor: '#FF5A1F', timezone: 'UTC', hasProfile: true },
      },
    })

    const { wrapper } = await mountView()
    const inputs = wrapper.findAll('input')

    // change name
    await inputs[0].setValue('New Name')
    // change height
    await inputs[2].setValue('175')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(profileApi.upsert).toHaveBeenCalledWith({
      gender: 'male',
      birthDate: '1990-05-15',
      heightCm: 175,
    })
    expect(authApi.updateMe).toHaveBeenCalledWith({ name: 'New Name' })
  })

  it('does not call updateMe when name is unchanged', async () => {
    vi.mocked(profileApi.get).mockResolvedValue({
      data: { gender: 'male', birthDate: '1990-05-15', heightCm: 180, tdeeKcal: null },
    })
    vi.mocked(profileApi.upsert).mockResolvedValue({
      data: { gender: 'male', birthDate: '1990-05-15', heightCm: 181, tdeeKcal: null },
    })

    const { wrapper } = await mountView()
    const inputs = wrapper.findAll('input')
    // change only height
    await inputs[2].setValue('181')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(profileApi.upsert).toHaveBeenCalled()
    expect(authApi.updateMe).not.toHaveBeenCalled()
  })
})
