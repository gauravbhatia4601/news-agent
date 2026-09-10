/**
 * Returns the canonical URL for the current route.
 * Combines the configured siteUrl with the current route path.
 */
export const useCanonical = () => {
  const { public: { siteUrl } } = useRuntimeConfig()
  const route = useRoute()
  return `${siteUrl}${route.path}`
}