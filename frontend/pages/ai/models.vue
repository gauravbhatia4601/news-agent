<script setup lang="ts">
import type { AiModelRow } from '~/components/ai/CostContextScatter.vue'
import type { AiModelsPayload } from '~/composables/useNewsApi'
import { providerColor } from '~/components/ai/providerColor'

const api = useNewsApi()
const { public: { siteUrl } } = useRuntimeConfig()

const { data: modelsPayload } = await useAsyncData<AiModelsPayload>(
  'ai-hub-models',
  () => api.getAiModels(),
  { default: () => ({ synced_at: null, models: [] }) },
)

// Table rows: model, provider, in/out, blended (3×in + 1×out), context.
interface TableRow {
  id: string
  name: string
  provider: string
  contextLength: number
  input: number | null
  output: number | null
  blended: number | null
}

const rows = computed<TableRow[]>(() =>
  (modelsPayload.value?.models ?? [])
    .filter((m) => (m.context_length ?? 0) > 0)
    .map((m) => {
      const input = m.input_price_per_million
      const output = m.output_price_per_million
      const blended = input !== null && output !== null ? input * 0.75 + output * 0.25 : null
      return {
        id: m.feed_id,
        name: m.name,
        provider: m.provider_name ?? m.provider ?? 'Unknown',
        contextLength: m.context_length as number,
        input,
        output,
        blended,
      }
    }),
)

const syncedRel = useRelativeTime(() => modelsPayload.value?.synced_at)

// ── Table state (native controls, no state lib, no URL params) ──
const search = ref('')
const providerFilter = ref('')
const sortKey = ref<'name' | 'input' | 'output' | 'blended' | 'context'>('blended')
const sortDir = ref<'asc' | 'desc'>('asc')
const page = ref(1)
const PER_PAGE = 25

const providers = computed(() =>
  [...new Set(rows.value.map((r) => r.provider))].sort((a, b) => a.localeCompare(b)),
)

function blendedOf(r: TableRow): number {
  return r.blended ?? r.input ?? r.output ?? Infinity
}

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  let out = rows.value
  if (q) out = out.filter((r) => r.name.toLowerCase().includes(q))
  if (providerFilter.value) out = out.filter((r) => r.provider === providerFilter.value)
  const dir = sortDir.value === 'asc' ? 1 : -1
  return [...out].sort((a, b) => {
    if (sortKey.value === 'name') return dir * a.name.localeCompare(b.name)
    const key = sortKey.value
    const av = key === 'context' ? a.contextLength : (a[key] ?? Infinity)
    const bv = key === 'context' ? b.contextLength : (b[key] ?? Infinity)
    return dir * (av - bv)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const pageRows = computed(() =>
  filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE),
)
const showingFrom = computed(() => (filtered.value.length === 0 ? 0 : (page.value - 1) * PER_PAGE + 1))
const showingTo = computed(() => Math.min(page.value * PER_PAGE, filtered.value.length))

function setSort(key: typeof sortKey.value): void {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = key === 'name' ? 'asc' : 'asc'
  }
  page.value = 1
}

function arrow(key: typeof sortKey.value): string {
  if (sortKey.value !== key) return '↕'
  return sortDir.value === 'asc' ? '↑' : '↓'
}

watch([search, providerFilter], () => {
  page.value = 1
})

function fmtPrice(v: number | null): string {
  return v === null ? '—' : (v >= 1 ? `$${v.toFixed(2)}` : `$${v.toFixed(2)}`)
}
function fmtContext(n: number): string {
  return n >= 1_000_000 ? `${(n / 1_000_000).toFixed(1)}M` : n >= 1000 ? `${Math.round(n / 1000)}k` : String(n)
}

const pageTitle = 'Model Pricing & Costs'
const pageDescription = 'Every tracked AI model ranked by price per million tokens — input, output, and blended cost — with context windows, searchable and sortable.'
const canonicalUrl = `${siteUrl}/ai/models`

usePageSeo(pageTitle, pageDescription)
useHead({
  titleTemplate: '%s — The Neural Journal',
  title: pageTitle,
  meta: [
    { name: 'description', content: pageDescription },
    { property: 'og:title', content: pageTitle },
    { property: 'og:description', content: pageDescription },
    { property: 'og:url', content: canonicalUrl },
    { name: 'twitter:title', content: pageTitle },
    { name: 'twitter:description', content: pageDescription },
  ],
  link: [
    { rel: 'canonical', href: canonicalUrl },
  ],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: () => JSON.stringify([
        {
          '@context': 'https://schema.org',
          '@type': 'CollectionPage',
          name: pageTitle,
          description: pageDescription,
          url: canonicalUrl,
        },
        {
          '@context': 'https://schema.org',
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Home', item: siteUrl },
            { '@type': 'ListItem', position: 2, name: 'AI', item: `${siteUrl}/ai` },
            { '@type': 'ListItem', position: 3, name: 'Pricing', item: canonicalUrl },
          ],
        },
      ]),
    },
  ],
})
</script>

<template>
  <div class="space-y-8">
    <header class="border-b pb-6">
      <div class="flex items-center gap-2 font-label text-xs text-muted-foreground mb-3">
        <NuxtLink to="/" class="hover:text-foreground transition-colors">Home</NuxtLink>
        <span>/</span>
        <NuxtLink to="/ai" class="hover:text-foreground transition-colors">AI</NuxtLink>
        <span>/</span>
        <span class="text-foreground font-medium">Pricing</span>
      </div>
      <h1 class="font-display text-3xl sm:text-4xl font-bold tracking-tight mb-2">
        Model Pricing &amp; Costs
      </h1>
      <p class="font-serif text-muted-foreground max-w-3xl">
        Every tracked model with its price per million tokens — input, output, blended (3×input + 1×output) — and how far each context window reaches. Search, filter, sort.
      </p>
    </header>

    <template v-if="rows.length > 0">
      <!-- Controls -->
      <div class="flex flex-wrap items-center gap-3">
        <label class="sr-only" for="model-search">Search models</label>
        <input
          id="model-search"
          v-model="search"
          type="search"
          placeholder="Search models…"
          class="border border-border bg-card px-3 py-2 font-label text-xs w-56 focus:outline-none focus:ring-1 focus:ring-ring"
        >
        <label class="sr-only" for="provider-filter">Filter by provider</label>
        <select
          id="provider-filter"
          v-model="providerFilter"
          class="border border-border bg-card px-3 py-2 font-label text-xs focus:outline-none focus:ring-1 focus:ring-ring"
        >
          <option value="">All providers</option>
          <option v-for="p in providers" :key="p" :value="p">{{ p }}</option>
        </select>
        <span class="font-label text-[11px] text-muted-foreground ml-auto">
          {{ filtered.length }} of {{ rows.length }} models
        </span>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full border-collapse font-label text-xs">
          <thead>
            <tr class="border-b border-border text-left">
              <th scope="col" class="py-2.5 pr-3 font-bold cursor-pointer select-none" @click="setSort('name')">
                Model <span class="text-muted-foreground">{{ arrow('name') }}</span>
              </th>
              <th scope="col" class="py-2.5 pr-3 font-bold hidden sm:table-cell">Provider</th>
              <th scope="col" class="py-2.5 pr-3 font-bold cursor-pointer select-none text-right" @click="setSort('input')">
                In $/M <span class="text-muted-foreground">{{ arrow('input') }}</span>
              </th>
              <th scope="col" class="py-2.5 pr-3 font-bold cursor-pointer select-none text-right" @click="setSort('output')">
                Out $/M <span class="text-muted-foreground">{{ arrow('output') }}</span>
              </th>
              <th scope="col" class="py-2.5 pr-3 font-bold cursor-pointer select-none text-right" @click="setSort('blended')">
                Blended <span class="text-muted-foreground">{{ arrow('blended') }}</span>
              </th>
              <th scope="col" class="py-2.5 font-bold cursor-pointer select-none text-right" @click="setSort('context')">
                Context <span class="text-muted-foreground">{{ arrow('context') }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in pageRows" :key="r.id" class="border-t border-border">
              <td class="py-2 pr-3">
                <span class="inline-flex items-center gap-2 min-w-0">
                  <span class="inline-block w-2 h-2 rounded-sm shrink-0" :style="{ background: providerColor(r.provider) }" />
                  <span class="truncate" :title="r.name">{{ r.name }}</span>
                </span>
              </td>
              <td class="py-2 pr-3 text-muted-foreground hidden sm:table-cell">{{ r.provider }}</td>
              <td class="py-2 pr-3 tabular-nums text-right">{{ fmtPrice(r.input) }}</td>
              <td class="py-2 pr-3 tabular-nums text-right">{{ fmtPrice(r.output) }}</td>
              <td class="py-2 pr-3 tabular-nums text-right font-bold">{{ fmtPrice(r.blended) }}</td>
              <td class="py-2 tabular-nums text-right text-muted-foreground">{{ fmtContext(r.contextLength) }}</td>
            </tr>
            <tr v-if="pageRows.length === 0">
              <td colspan="6" class="py-8 text-center font-serif text-muted-foreground">No models match.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-between font-label text-xs text-muted-foreground">
        <span>Showing {{ showingFrom }}–{{ showingTo }} of {{ filtered.length }}</span>
        <span class="flex items-center gap-2">
          <button
            type="button"
            :disabled="page <= 1"
            class="border border-border px-3 py-1.5 hover:text-foreground disabled:opacity-40 disabled:cursor-not-allowed"
            @click="page--"
          >
            ← Prev
          </button>
          <span class="tabular-nums">{{ page }} / {{ totalPages }}</span>
          <button
            type="button"
            :disabled="page >= totalPages"
            class="border border-border px-3 py-1.5 hover:text-foreground disabled:opacity-40 disabled:cursor-not-allowed"
            @click="page++"
          >
            Next →
          </button>
        </span>
      </div>

      <!-- Scatter stays as the visual companion, below the table -->
      <section class="border border-border p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-display text-lg font-bold">Cost vs Context Window</h2>
          <span class="font-label text-[11px] text-muted-foreground">log scale · best value zone shaded</span>
        </div>
        <AiCostContextScatter :models="rows.map((r) => ({
          id: r.id,
          name: r.name,
          provider: r.provider,
          contextLength: r.contextLength,
          pricePerMInput: r.input,
          pricePerMOutput: r.output,
        }))" />
      </section>

      <p class="font-label text-[11px] text-muted-foreground">
        Source: OpenRouter · synced {{ syncedRel }}
      </p>
    </template>

    <p v-else class="py-16 text-center font-serif text-muted-foreground">
      Model data is syncing — check back shortly.
    </p>
  </div>
</template>