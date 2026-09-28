<script setup lang="ts">
import type { AiModelRow } from '~/components/ai/CostContextScatter.vue'
import type { AiModelsPayload } from '~/composables/useNewsApi'

const api = useNewsApi()
const { public: { siteUrl } } = useRuntimeConfig()

const { data: modelsPayload } = await useAsyncData<AiModelsPayload>(
  'ai-hub-models',
  () => api.getAiModels(),
  { default: () => ({ synced_at: null, models: [] }) },
)

// Chart-row shape shared with the hub page.
const rows = computed<AiModelRow[]>(() =>
  (modelsPayload.value?.models ?? [])
    .map((m) => ({
      id: m.feed_id,
      name: m.name,
      provider: m.provider_name ?? m.provider ?? 'Unknown',
      contextLength: m.context_length ?? 0,
      pricePerMInput: m.input_price_per_million,
      pricePerMOutput: m.output_price_per_million,
      intelligenceIndex: m.intelligence_index,
      modality: m.modality ?? undefined,
    }))
    .filter((m) => m.contextLength > 0),
)

const syncedRel = useRelativeTime(() => modelsPayload.value?.synced_at)

const pageTitle = 'Model Pricing & Costs'
const pageDescription = 'Every tracked AI model ranked by price per million tokens — input, output, and blended cost — with context windows, from the live model catalog.'
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
        Every tracked model ranked by price — blended (3×input + 1×output), raw input and output rates, and how far each context window reaches.
      </p>
    </header>

    <template v-if="rows.length > 0">
      <section class="border border-border p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-display text-xl font-bold">Ranked by Price</h2>
          <span class="font-label text-[11px] text-muted-foreground">$ per 1M tokens · log scale</span>
        </div>
        <AiModelPriceBars :models="rows.filter((m) => (m.pricePerMInput ?? m.pricePerMOutput ?? 0) > 0)" />
        <AiProviderLegend :models="rows" />
      </section>

      <section class="border border-border p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-display text-lg font-bold">Cost vs Context Window</h2>
          <span class="font-label text-[11px] text-muted-foreground">log scale · best value zone shaded</span>
        </div>
        <AiCostContextScatter :models="rows" />
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