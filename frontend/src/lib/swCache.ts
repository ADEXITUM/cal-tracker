/**
 * Per-user "honest offline": when the active account changes we drop the
 * Workbox runtime caches that hold per-user API responses. Cache keys there
 * are URL-only, so without this purge the next user could be served the
 * previous user's cached response (most visibly /auth/me on switchTo).
 *
 * The current user's day-by-date cache lives in IDB under user-scoped keys
 * (see lib/dayCache.ts) and survives this purge — they keep offline access
 * to days they've opened.
 */
const API_CACHES = ['api-days', 'api-stats', 'api-static']

export async function clearApiCaches(): Promise<void> {
  if (typeof caches === 'undefined') return
  await Promise.all(API_CACHES.map((name) => caches.delete(name).catch(() => false)))
}
