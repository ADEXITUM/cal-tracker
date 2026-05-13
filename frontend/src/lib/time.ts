/**
 * Time-unit constants. Keep all "× 86400" / "× 7" arithmetic out of feature
 * code — express it via these names instead.
 */
export const SECONDS_PER_DAY = 86_400
export const MS_PER_DAY      = 86_400_000
export const DAYS_PER_WEEK   = 7

/** Used in date arithmetic when building lookback ranges (e.g. "last N days"). */
export function daysAgoIso(days: number, base: Date = new Date()): string {
  return new Date(base.getTime() - days * MS_PER_DAY).toISOString().slice(0, 10)
}

/** ISO date for the day before the given ISO date (UTC midday anchor). */
export function previousDayIso(iso: string): string {
  const d = new Date(iso + 'T12:00:00')
  d.setDate(d.getDate() - 1)
  return d.toISOString().slice(0, 10)
}

/**
 * "Logical day" cutoff — must match backend `App\Support\LogicalDate::CUTOFF_HOUR`.
 * Times before 03:00 local count as the previous day so late-night meals stay
 * in yesterday's diary instead of flipping to a fresh empty page at midnight.
 * Make the cutoff explicit (not "happens to work because of UTC offset")
 * so that users in non-Moscow timezones get the same behaviour.
 */
export const LOGICAL_DAY_CUTOFF_HOUR = 3

/**
 * Logical date (YYYY-MM-DD in local time) for a given instant. Pure
 * date-math: shift the moment back by CUTOFF_HOUR, then take its local
 * calendar date.
 */
export function logicalDateIso(date: Date = new Date()): string {
  const shifted = new Date(date.getTime() - LOGICAL_DAY_CUTOFF_HOUR * 60 * 60 * 1000)
  const y = shifted.getFullYear()
  const m = String(shifted.getMonth() + 1).padStart(2, '0')
  const d = String(shifted.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

/**
 * Human label for a meal's eaten_at, anchored to the logical day:
 *   today  → "сегодня 14:30"
 *   yesterday → "вчера 22:30"
 *   older → "12 мая, 14:30"
 */
export function formatRelativeDateTime(iso: string, now: Date = new Date()): string {
  const d = new Date(iso)
  const time = d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
  const dKey = logicalDateIso(d)
  const todayKey = logicalDateIso(now)
  const yesterdayKey = logicalDateIso(new Date(now.getTime() - MS_PER_DAY))

  if (dKey === todayKey) return `сегодня ${time}`
  if (dKey === yesterdayKey) return `вчера ${time}`
  const datePart = d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' })
  return `${datePart}, ${time}`
}
