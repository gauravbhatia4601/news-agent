<script setup lang="ts">
import type { NewsArticleCard } from '~/types/news'
import type { AiModelRow } from '~/components/ai/CostContextScatter.vue'
import type { AiModelsPayload } from '~/composables/useNewsApi'

const api = useNewsApi()
const { public: { siteUrl } } = useRuntimeConfig()

// ── Model intelligence (live, from GET /api/v1/ai/models — DB-backed, ADR 0001) ──
const { data: modelsPayload } = await useAsyncData<AiModelsPayload>(
  'ai-hub-models',
  () => api.getAiModels(),
  { default: () => ({ synced_at: null, models: [] }) },
)

// Both charts consume the chart-row shape; featured rows are the curated
// flagship set (backend flag). Rows without a parseable price/context are
// dropped — the charts can't plot them.
const featuredRows = computed<AiModelRow[]>(() =>
  (modelsPayload.value?.models ?? [])
    .filter((m) => m.featured)
    .map((m) => ({
      id: m.feed_id,
      name: m.name,
      provider: m.provider_name ?? m.provider ?? 'Unknown',
      contextLength: m.context_length ?? 0,
      pricePerMInput: m.input_price_per_million,
      pricePerMOutput: m.output_price_per_million,
      modality: m.modality ?? undefined,
    }))
    .filter((m) => m.contextLength > 0),
)

const syncedRel = useRelativeTime(() => modelsPayload.value?.synced_at)

// ── Latest AI news (existing category feed pattern) ──
const perPage = 10
const { data: pageData } = await useAsyncData(
  'ai-hub-articles',
  () => api.getLatestPaginated({ category: 'artificial-intelligence', perPage, page: 1 }),
  { default: () => ({ data: [] as NewsArticleCard[], meta: { current_page: 1, last_page: 1, total: 0 } }) },
)
const articles = computed<NewsArticleCard[]>(() => pageData.value?.data ?? [])
const heroRel = useRelativeTime(() => articles.value[0]?.published_at)

const pageTitle = 'AI News & Model Intelligence'
const pageDescription = 'The latest AI news with live model intelligence: flagship model prices per million tokens, context windows, and cost comparisons across top AI providers.'
const canonicalUrl = `${siteUrl}/ai`

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
          isPartOf: { '@type': 'WebSite', name: 'The Neural Journal', url: siteUrl },
        },
        {
          '@context': 'https://schema.org',
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Home', item: siteUrl },
            { '@type': 'ListItem', position: 2, name: 'AI', item: canonicalUrl },
          ],
        },
      ]),
    },
  ],
})
</script>

<template>
  <div class="space-y-8">
    <!-- Breadcrumb + header -->
    <header class="border-b pb-6">
      <div class="flex items-center gap-2 font-label text-xs text-muted-foreground mb-3">
        <NuxtLink to="/" class="hover:text-foreground transition-colors">Home</NuxtLink>
        <span>/</span>
        <span class="text-foreground font-medium">AI</span>
      </div>
      <h1 class="font-display text-3xl sm:text-4xl font-bold tracking-tight mb-2">
        AI News &amp; Model Intelligence
      </h1>
      <p class="font-serif text-muted-foreground max-w-3xl">
        The latest AI news — model launches, price changes, benchmarks, policy and research — alongside live model intelligence: what flagship models cost per million tokens and how far their context windows reach, tracked continuously.
      </p>
    </header>

    <h2 class="sr-only">Model intelligence</h2>

    <!-- Model Intelligence data panel (hidden when the catalog is empty) -->
    <template v-if="featuredRows.length > 0">
      <section class="border border-border p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-display text-xl font-bold">Flagship Model Pricing</h3>
          <span class="font-label text-[11px] text-muted-foreground">input price per 1M tokens</span>
        </div>
        <AiModelPriceBars :models="featuredRows" />
      </section>

      <section class="border border-border p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-display text-lg font-bold">Cost vs Context Window</h3>
          <span class="font-label text-[11px] text-muted-foreground">log scale</span>
        </div>
        <AiCostContextScatter :models="featuredRows" />
      </section>

      <p class="font-label text-[11px] text-muted-foreground">
        Source: OpenRouter · synced {{ syncedRel }}
      </p>
    </template>

    <!-- Latest AI news -->
    <section>
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-display text-xl font-bold flex items-center gap-4">
          Latest AI News
          <span class="h-px flex-1 bg-border" />
        </h2>
        <NuxtLink to="/category/artificial-intelligence" class="text-xs text-muted-foreground hover:text-foreground font-label uppercase tracking-[0.062em]">
          Full archive →
        </NuxtLink>
      </div>

      <div v-if="articles.length === 0" class="py-16 text-center font-serif text-muted-foreground">
        No AI articles yet — new coverage appears as stories break.
      </div>

      <template v-else>
        <!-- Hero lead (first article) -->
        <section v-if="articles[0]" class="group mb-8">
          <NuxtLink :to="`/article/${articles[0].slug}`" class="block">
            <div v-if="articles[0].image_url" class="mb-4 overflow-hidden">
              <img
                :src="articles[0].image_url"
                :alt="articles[0].title"
                class="h-64 w-full object-cover sm:h-80 transition-transform duration-500 group-hover:scale-[1.02]"
                loading="eager"
              >
            </div>
            <div class="flex items-center gap-2 mb-2">
              <span class="font-label text-[11px] font-bold uppercase tracking-[0.062em] text-muted-foreground">{{ articles[0].category?.name ?? '' }}</span>
              <span class="text-border">|</span>
              <span class="font-label text-[11px] text-muted-foreground">{{ heroRel }}</span>
              <span v-if="isNew(articles[0].published_at)" class="font-label text-[10px] font-bold uppercase tracking-[0.062em] text-secondary">New</span>
            </div>
            <h2 class="font-display text-2xl sm:text-3xl font-bold leading-[1.15] mb-3 group-hover:underline decoration-1 underline-offset-4">
              {{ articles[0].title }}
            </h2>
            <p v-if="articles[0].excerpt" class="font-serif text-sm sm:text-base leading-relaxed text-muted-foreground mb-4 max-w-3xl">
              {{ articles[0].excerpt }}
            </p>
          </NuxtLink>
        </section>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-8">
          <NewsCompactArticleCard
            v-for="article in articles.slice(1)"
            :key="article.slug"
            :article="article"
            variant="default"
            :show-image="!!article.image_url || !!article.thumbnail_url"
          />
        </div>
      </template>
    </section>
  </div>
</template>