import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import 'fake-indexeddb/auto'

// We import inside the tests so we can re-import the module to reset internal singleton state.

async function loadModule() {
  vi.resetModules()
  return await import('../useOfflineQueue')
}

describe('useOfflineQueue', () => {
  beforeEach(() => {
    // Reset fake-indexeddb between tests
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('enqueue adds an action with createdAt + 0 attempts', async () => {
    const fetchSpy = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: { uuid: 'real-1' } }), { status: 201 }))
    vi.stubGlobal('fetch', fetchSpy)

    const mod = await loadModule()
    mod.configureOfflineQueue({ getToken: () => 'tok', getCurrentUserUuid: () => 'u-1' })
    await mod.enqueue({
      id: 'a-1',
      method: 'POST',
      url: '/days/2026-04-30/meals',
      body: { kcal: 100 },
    })

    // It should have called fetch since we're online by default
    await new Promise(r => setTimeout(r, 5))
    expect(fetchSpy).toHaveBeenCalledWith(
      '/api/v1/days/2026-04-30/meals',
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({
          'Idempotency-Key': 'a-1',
          Authorization: 'Bearer tok',
        }),
      }),
    )
  })

  it('drops 4xx as permanent failure and removes from queue', async () => {
    const fetchSpy = vi
      .fn()
      .mockResolvedValue(new Response(JSON.stringify({ message: 'invalid' }), { status: 422 }))
    vi.stubGlobal('fetch', fetchSpy)

    const onFail = vi.fn()
    const mod = await loadModule()
    mod.configureOfflineQueue({ getToken: () => 't', getCurrentUserUuid: () => 'u-1', onTerminalFailure: onFail })

    await mod.enqueue({ id: 'b-1', method: 'POST', url: '/x', body: {} })
    await new Promise(r => setTimeout(r, 10))

    const { queue } = mod.useOfflineQueue()
    expect(queue.value).toHaveLength(0)
    expect(onFail).toHaveBeenCalledOnce()
  })

  it('does not process when offline', async () => {
    vi.stubGlobal('navigator', { onLine: false } as Navigator)
    const fetchSpy = vi.fn()
    vi.stubGlobal('fetch', fetchSpy)

    const mod = await loadModule()
    mod.configureOfflineQueue({ getToken: () => 't' })

    await mod.enqueue({ id: 'c-1', method: 'POST', url: '/x', body: {} })
    await new Promise(r => setTimeout(r, 5))

    expect(fetchSpy).not.toHaveBeenCalled()
    const { queue } = mod.useOfflineQueue()
    expect(queue.value).toHaveLength(1)
  })

  it('keeps action in queue on transient failure (5xx) up to backoff cap', async () => {
    // Mock fetch returning 500 for any attempt
    vi.stubGlobal('navigator', { onLine: true } as Navigator)
    const fetchSpy = vi.fn().mockResolvedValue(new Response('boom', { status: 500 }))
    vi.stubGlobal('fetch', fetchSpy)

    const mod = await loadModule()
    mod.configureOfflineQueue({ getToken: () => 't', getCurrentUserUuid: () => 'u-1' })

    await mod.enqueue({ id: 'd-1', method: 'POST', url: '/x', body: {} })
    // Wait long enough for the very first backoff (1s) attempt cycle
    await new Promise(r => setTimeout(r, 1100))

    const { queue } = mod.useOfflineQueue()
    expect(queue.value).toHaveLength(1)
    expect(queue.value[0].attempts).toBeGreaterThanOrEqual(1)
    expect(queue.value[0].lastError).toMatch(/HTTP 500/)
  }, 10000)

  it('does not send actions belonging to a different user', async () => {
    vi.stubGlobal('navigator', { onLine: true } as Navigator)
    const fetchSpy = vi.fn().mockResolvedValue(new Response('{}', { status: 200 }))
    vi.stubGlobal('fetch', fetchSpy)

    const mod = await loadModule()
    let activeUuid: string | null = 'u-A'
    mod.configureOfflineQueue({ getToken: () => 't', getCurrentUserUuid: () => activeUuid })

    // Enqueue under user A, then "switch" before processQueue runs the next round.
    await mod.enqueue({ id: 'A-1', method: 'POST', url: '/x', body: {} })
    await new Promise(r => setTimeout(r, 5))
    expect(fetchSpy).toHaveBeenCalledTimes(1) // A's action sent under A

    // Switch to B with no pending actions belonging to B; A's pending writes
    // (none left) shouldn't leak. Simulate by enqueuing under A while B is active:
    activeUuid = 'u-B'
    // Force-add an action with userUuid='u-A' through the public enqueue;
    // the queue's own guard should keep it pinned.
    await mod.enqueue({ id: 'A-2', method: 'POST', url: '/x', body: {}, userUuid: 'u-A' })
    await new Promise(r => setTimeout(r, 20))
    // Still only the first call (A-1). A-2 stays queued waiting for u-A.
    expect(fetchSpy).toHaveBeenCalledTimes(1)
    const { queue } = mod.useOfflineQueue()
    expect(queue.value.find(a => a.id === 'A-2')).toBeDefined()
  })

  it('caps queue at MAX_QUEUE_SIZE by dropping oldest', async () => {
    vi.stubGlobal('navigator', { onLine: false } as Navigator) // keep them in queue
    vi.stubGlobal('fetch', vi.fn())

    const mod = await loadModule()
    mod.configureOfflineQueue({ getToken: () => 't' })

    // Enqueue 102, expect at most 100 (with the 2 newest staying)
    for (let i = 0; i < 102; i++) {
      await mod.enqueue({ id: `x-${i}`, method: 'POST', url: '/x', body: { i } })
    }
    const { queue } = mod.useOfflineQueue()
    expect(queue.value.length).toBeLessThanOrEqual(100)
    // First (oldest surviving) item should be x-2
    expect(queue.value[0].id).toBe('x-2')
  })
})
