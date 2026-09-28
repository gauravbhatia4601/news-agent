// Provider → swatch color for the /ai charts. Fixed map for the known labs;
// unknown providers fall back to a neutral gray. Both sides of the lookup are
// normalized to [a-z0-9] (same rule as utils/modelSearch.ts): the sync now
// stores display names ('xAI', 'Meta', 'Mistral'), legacy DB rows still carry
// raw slugs ('x-ai', 'meta-llama'), and stale pre-canonicalization rows carry
// a leading '~' — none of those may regress a bar to gray.
const PROVIDER_COLORS: Record<string, string> = {
  openai: '#10a37f',
  anthropic: '#d97757',
  google: '#4285f4',
  xai: '#1f2937',
  deepseek: '#4d6bfe',
  meta: '#8b5cf6',
  metallama: '#8b5cf6',
  qwen: '#e11d48',
  moonshotai: '#0891b2',
  mistral: '#ea580c',
  mistralai: '#ea580c',
}

export function providerColor(provider: string | null | undefined): string {
  if (!provider) return '#6b7280'
  const key = provider.toLowerCase().replace(/[^a-z0-9]/g, '')
  return PROVIDER_COLORS[key] ?? '#6b7280'
}