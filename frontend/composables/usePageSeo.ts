/**
 * Shared OG/Twitter completeness for static pages (trending, stories,
 * categories, about, contact). Ahrefs flagged 192 pages whose OG tags were
 * incomplete — they set plain meta description/title but no og:title/
 * og:description/og:url mirrors. og:image + twitter:card come from the
 * app.head defaults.
 */
export function usePageSeo(title: string, description: string) {
  const canonical = useCanonical()
  useHead({
    meta: [
      { property: 'og:title', content: title },
      { property: 'og:description', content: description },
      { property: 'og:url', content: canonical },
      { name: 'twitter:title', content: title },
      { name: 'twitter:description', content: description },
    ],
  })
}