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
