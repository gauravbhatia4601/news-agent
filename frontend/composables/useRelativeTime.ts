/**
 * Hydration-safe relative time.
 *
 * The ref initializer runs during setup on BOTH server and client, producing the
 * same deterministic absolute date via formatAbsolute (UTC parts parsed from the
 * ISO string — no timezone shift). The initial client render therefore matches
 * the SSR HTML exactly. The timeAgo swap happens only in onMounted, which fires
 * AFTER hydration completes — no mismatch is possible.
 *
 * Usage (single value, not v-for):
 *   const publishedRel = useRelativeTime(() => props.article.published_at)
 *   // template: {{ publishedRel }}
 *
 * For v-for lists where a composable cannot be called per-item, use formatAbsolute
 * directly with a local isMounted flag instead.
 */
export function useRelativeTime(getDate: () => string | undefined | null) {
  const value = ref(formatAbsolute(getDate() ?? ''))
  onMounted(() => {
    const d = getDate()
    if (d) value.value = timeAgo(d)
  })
  return value
}