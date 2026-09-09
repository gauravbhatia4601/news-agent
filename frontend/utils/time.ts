export function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const hours = Math.floor(diff / 3600000)
  if (hours < 1) return 'Just now'
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return days === 1 ? '1 day ago' : `${days} days ago`
}

export function isNew(dateStr: string, withinHours?: number): boolean {
  const threshold = withinHours ?? (useRuntimeConfig().public.newBadgeHours as number | undefined) ?? 6
  return Date.now() - new Date(dateStr).getTime() < threshold * 3600000
}