/**
 * AI-model search matcher: matches a row's NAME or PROVIDER, so provider
 * strings ("deepseek", "z-ai", "~deepseek") find models the way the provider
 * dropdown lists them. Root cause of the "search is broken" report (2026-09-28):
 * the inline matcher compared model name only.
 *
 * Both sides are lowercased and stripped to [a-z0-9] so punctuation
 * differences never block a match — "~deepseek" matches the "DeepSeek"
 * provider, "gpt 5" matches "GPT-5.6". Model/provider strings are latin, so
 * the [a-z0-9] strip loses nothing.
 */
export function matchesSearch(
  row: { name: string; provider: string },
  query: string,
): boolean {
  const q = query.toLowerCase().replace(/[^a-z0-9]/g, '')
  if (!q) return true
  const name = row.name.toLowerCase().replace(/[^a-z0-9]/g, '')
  const provider = row.provider.toLowerCase().replace(/[^a-z0-9]/g, '')
  return name.includes(q) || provider.includes(q)
}