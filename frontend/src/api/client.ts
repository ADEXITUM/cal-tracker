export class ValidationError extends Error {
  constructor(public readonly errors: Record<string, string[]>) {
    super('Validation failed')
  }
}

function toCamel(s: string): string {
  return s.replace(/_([a-z])/g, (_, c: string) => c.toUpperCase())
}

function toSnake(s: string): string {
  return s.replace(/[A-Z]/g, (c) => `_${c.toLowerCase()}`)
}

function transformKeys(obj: unknown, transform: (k: string) => string): unknown {
  if (Array.isArray(obj)) return obj.map((v) => transformKeys(v, transform))
  if (obj !== null && typeof obj === 'object') {
    return Object.fromEntries(
      Object.entries(obj).map(([k, v]) => [transform(k), transformKeys(v, transform)]),
    )
  }
  return obj
}

export function camelizeResponse<T>(data: unknown): T {
  return transformKeys(data, toCamel) as T
}

export function snakeifyRequest(data: unknown): unknown {
  return transformKeys(data, toSnake)
}

type OnUnauthorized = () => void

let _onUnauthorized: OnUnauthorized = () => {}
let _getToken: () => string | null = () => null

export function configureClient(opts: { getToken: () => string | null; onUnauthorized: OnUnauthorized }) {
  _getToken = opts.getToken
  _onUnauthorized = opts.onUnauthorized
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  opts?: { idempotencyKey?: string },
): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  }
  const token = _getToken()
  if (token) headers['Authorization'] = `Bearer ${token}`
  if (opts?.idempotencyKey) headers['Idempotency-Key'] = opts.idempotencyKey

  const res = await fetch(`/api/v1${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(snakeifyRequest(body)) : undefined,
  })

  if (res.status === 401) {
    // Only fire onUnauthorized when we actually had a token (avoid recursion during logout)
    if (_getToken()) _onUnauthorized()
    throw new Error('Unauthenticated')
  }

  if (res.status === 204) return undefined as T

  const json = await res.json()

  if (res.status === 422) {
    throw new ValidationError(camelizeResponse<Record<string, string[]>>(json.errors ?? {}))
  }

  if (!res.ok) {
    throw new Error(json.message ?? `HTTP ${res.status}`)
  }

  return camelizeResponse<T>(json)
}

export const api = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown, opts?: { idempotencyKey?: string }) =>
    request<T>('POST', path, body, opts),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
  delete: <T>(path: string) => request<T>('DELETE', path),
}
