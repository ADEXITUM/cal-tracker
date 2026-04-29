import { ref, computed } from 'vue'
import { get as idbGet, set as idbSet } from 'idb-keyval'

const QUEUE_KEY = 'offline_queue_v1'
const MAX_QUEUE_SIZE = 100

export interface QueuedAction {
  id: string                 // UUID v4 = idempotency key
  url: string                // path under /api/v1, e.g. /days/2026-04-30/meals
  method: 'POST' | 'PUT' | 'DELETE'
  body: unknown
  createdAt: number
  attempts: number
  lastError: string | null
}

const queue = ref<QueuedAction[]>([])
const processing = ref(false)
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)
let initialized = false

const BACKOFF_MS = [1000, 5000, 30000, 300000]

let getToken: () => string | null = () => null
let onSuccess: (action: QueuedAction, response: unknown) => void = () => {}
let onTerminalFailure: (action: QueuedAction, message: string) => void = () => {}

export function configureOfflineQueue(opts: {
  getToken: () => string | null
  onSuccess?: (action: QueuedAction, response: unknown) => void
  onTerminalFailure?: (action: QueuedAction, message: string) => void
}) {
  getToken = opts.getToken
  if (opts.onSuccess) onSuccess = opts.onSuccess
  if (opts.onTerminalFailure) onTerminalFailure = opts.onTerminalFailure
}

async function load(): Promise<void> {
  const raw = await idbGet<QueuedAction[]>(QUEUE_KEY).catch(() => null)
  queue.value = raw ?? []
}

async function persist(): Promise<void> {
  try { await idbSet(QUEUE_KEY, queue.value) } catch { /* IDB unavailable */ }
}

export async function enqueue(action: Omit<QueuedAction, 'createdAt' | 'attempts' | 'lastError'>): Promise<void> {
  await ensureInitialized()
  if (queue.value.length >= MAX_QUEUE_SIZE) {
    queue.value.shift() // drop oldest
  }
  queue.value.push({
    ...action,
    createdAt: Date.now(),
    attempts: 0,
    lastError: null,
  })
  await persist()
  void processQueue()
}

export async function processQueue(): Promise<void> {
  await ensureInitialized()
  if (processing.value) return
  if (!isOnline.value) return
  if (queue.value.length === 0) return

  processing.value = true
  try {
    while (queue.value.length > 0 && isOnline.value) {
      const action = queue.value[0]
      const result = await sendOnce(action)
      if (result.kind === 'success') {
        queue.value.shift()
        await persist()
        try { onSuccess(action, result.response) } catch { /* noop */ }
      } else if (result.kind === 'permanent') {
        queue.value.shift()
        await persist()
        try { onTerminalFailure(action, result.message) } catch { /* noop */ }
      } else {
        // transient — bump attempts and back off
        action.attempts += 1
        action.lastError = result.message
        await persist()
        const delay = BACKOFF_MS[Math.min(action.attempts - 1, BACKOFF_MS.length - 1)]
        await new Promise(r => setTimeout(r, delay))
        if (action.attempts >= BACKOFF_MS.length) {
          // Stop processing this round; will retry on next online event
          break
        }
      }
    }
  } finally {
    processing.value = false
  }
}

async function sendOnce(action: QueuedAction): Promise<
  | { kind: 'success'; response: unknown }
  | { kind: 'permanent'; message: string }
  | { kind: 'transient'; message: string }
> {
  try {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Idempotency-Key': action.id,
    }
    const token = getToken()
    if (token) headers['Authorization'] = `Bearer ${token}`

    const res = await fetch(`/api/v1${action.url}`, {
      method: action.method,
      headers,
      body: action.method === 'DELETE' ? undefined : JSON.stringify(action.body),
    })

    if (res.status >= 200 && res.status < 300) {
      const json = res.status === 204 ? null : await res.json().catch(() => null)
      return { kind: 'success', response: json }
    }
    if (res.status === 401) {
      // Auth lost — drop action and surface; user will re-login
      return { kind: 'permanent', message: 'Не авторизован' }
    }
    if (res.status >= 400 && res.status < 500) {
      // 4xx — validation or conflict; can't retry
      return { kind: 'permanent', message: `Ошибка ${res.status}` }
    }
    return { kind: 'transient', message: `HTTP ${res.status}` }
  } catch (e) {
    // Network error
    return { kind: 'transient', message: (e as Error).message ?? 'Network error' }
  }
}

async function ensureInitialized(): Promise<void> {
  if (initialized) return
  initialized = true
  await load()
  if (typeof window !== 'undefined') {
    window.addEventListener('online', () => {
      isOnline.value = true
      void processQueue()
    })
    window.addEventListener('offline', () => {
      isOnline.value = false
    })
  }
}

export function useOfflineQueue() {
  void ensureInitialized()
  return {
    queue,
    pendingCount: computed(() => queue.value.length),
    processing,
    isOnline,
    enqueue,
    processQueue,
  }
}
