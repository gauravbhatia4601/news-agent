// Provider → swatch color for the /ai charts. Fixed map for the known labs;
// unknown providers fall back to a neutral gray. Keys are the lowercase
// provider slugs stored by the sync (openai, anthropic, …).
const PROVIDER_COLORS: Record<string, string> = {
  openai: '#10a37f',
  anthropic: '#d97757',
  google: '#4285f4',
  'x-ai': '#1f2937',
  deepseek: '#4d6bfe',
  'meta-llama': '#8b5cf6',
  qwen: '#e11d48',
  moonshotai: '#0891b2',
  mistralai: '#ea580c',
}

export function providerColor(provider: string | null | undefined): string {
  if (!provider) return '#6b7280'
  return PROVIDER_COLORS[provider.toLowerCase()] ?? '#6b7280'
}