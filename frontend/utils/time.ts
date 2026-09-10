export function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const hours = Math.floor(diff / 3600000)
  if (hours < 1) return 'Just now'
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return days === 1 ? '1 day ago' : `${days} days ago`
}

/**
 * Deterministic absolute date from an ISO 8601 string.
 * Parses the ISO string and formats UTC parts directly — never uses Date
 * methods that shift by timezone. Returns "MMM D, HH:mm" (e.g. "Sep 11, 14:30").
 * Server and client produce the identical string, preventing hydration mismatches.
 */
export function formatAbsolute(dateStr: string): string {
  const m = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/)
  if (!m) return ''
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${months[Number(m[2]) - 1]} ${Number(m[3])}, ${m[4]}:${m[5]}`
}

export function isNew(dateStr: string, withinHours?: number): boolean {
  const threshold = withinHours ?? (useRuntimeConfig().public.newBadgeHours as number | undefined) ?? 6
  return Date.now() - new Date(dateStr).getTime() < threshold * 3600000
}