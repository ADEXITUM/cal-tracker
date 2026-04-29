import { get as idbGet, set as idbSet, del as idbDel, keys as idbKeys } from 'idb-keyval'
import type { DayResource } from '@/types/api'

const KEY_PREFIX = 'day_cache_v1::'
const KEEP_DAYS = 7

interface CachedDay {
  data: DayResource
  cachedAt: number
  userUuid: string
}

function key(userUuid: string, date: string): string {
  return `${KEY_PREFIX}${userUuid}::${date}`
}

export async function readCachedDay(userUuid: string, date: string): Promise<DayResource | null> {
  if (!userUuid) return null
  const entry = await idbGet<CachedDay>(key(userUuid, date)).catch(() => null)
  return entry?.data ?? null
}

export async function writeCachedDay(userUuid: string, date: string, data: DayResource): Promise<void> {
  if (!userUuid) return
  try {
    await idbSet(key(userUuid, date), {
      data,
      cachedAt: Date.now(),
      userUuid,
    } satisfies CachedDay)
    void pruneOldDays(userUuid)
  } catch {
    // IDB unavailable
  }
}

async function pruneOldDays(userUuid: string): Promise<void> {
  try {
    const allKeys = await idbKeys()
    const userPrefix = `${KEY_PREFIX}${userUuid}::`
    const userKeys = allKeys
      .filter((k): k is string => typeof k === 'string' && k.startsWith(userPrefix))
      .sort() // dates are ISO so lex sort = chronological
    const excess = userKeys.length - KEEP_DAYS
    if (excess > 0) {
      await Promise.all(userKeys.slice(0, excess).map(k => idbDel(k)))
    }
  } catch {
    // best effort
  }
}

export async function clearUserCache(userUuid: string): Promise<void> {
  try {
    const allKeys = await idbKeys()
    const userPrefix = `${KEY_PREFIX}${userUuid}::`
    const userKeys = allKeys.filter(
      (k): k is string => typeof k === 'string' && k.startsWith(userPrefix),
    )
    await Promise.all(userKeys.map(k => idbDel(k)))
  } catch {
    // best effort
  }
}
