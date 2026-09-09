export function dedupeBySlug<T extends { slug: string }>(items: T[], seen: Set<string>): T[] {
  return items.filter(a => { if (seen.has(a.slug)) return false; seen.add(a.slug); return true })
}