export function StrLimit(value: string | null, limit: number, end = '…'): string {
  if (!value) return ''
  if (value.length <= limit) return value
  return value.slice(0, limit) + end
}
