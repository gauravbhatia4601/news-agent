<script setup lang="ts">
const api = useNewsApi()

// Over-fetch by 1 so dedup can drop collisions without leaving sections thin.
const { data: featured } = await useAsyncData('featured', () => api.getFeatured())
const { data: headlines } = await useAsyncData('headlines', () => api.getHeadlines(7), { default: () => [] as any[] })
const { data: hot } = await useAsyncData('hot', () => api.getHot({ perPage: 7 }), { default: () => [] as any[] })
const { data: trending } = await useAsyncData('trending-home', () => api.getTrending(5), { default: () => [] as any[] })
const { data: categoryTree } = await useAsyncData('home-category-tree', () => api.getCategoryTree(), { default: () => [] as any[] })

// ponytail: one getHeadlines per top-level category; ceiling = category count (no single endpoint buckets by category)
const { data: categorySections } = await useAsyncData(
  'home-category-headlines',
  async () => {
    if (!categoryTree.value?.length) return []
    return Promise.all(
      categoryTree.value.map(async (cat: any) => ({
        name: cat.name,
        slug: cat.slug,
        articles: await api.getHeadlines(5, cat.slug),
      })),
    )
  },
  { default: () => [] as { name: string; slug: string; articles: any[] }[] },
)

// Priority dedup: featured → hot → headlines → trending → categories.
// featured is a single article; claim its slug first so it never repeats.
const deduped = computed(() => {
  const seen = new Set<string>()
  if (featured.value?.slug) seen.add(featured.value.slug)

  const hotD = dedupeBySlug(hot.value ?? [], seen).slice(0, 6)
  const headlinesD = dedupeBySlug(headlines.value ?? [], seen).slice(0, 6)
  const trendingD = dedupeBySlug(trending.value ?? [], seen).slice(0, 4)

  const sections = (categorySections.value ?? []).map(section => ({
    name: section.name,
    slug: section.slug,
    articles: dedupeBySlug(section.articles ?? [], seen).slice(0, 4),
  }))

  return { hot: hotD, headlines: headlinesD, trending: trendingD, sections }
})

useHead({
  title: 'The Neural Journal — Latest News from India',
  meta: [
    { name: 'description', content: 'Stay informed with the latest news across India — politics, business, technology, sports, entertainment and more.' },
  ],
})
</script>

<template>
  <div class="space-y-10">
    <h1 class="sr-only">The Neural Journal</h1>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
      <div class="lg:col-span-8 space-y-10">
        <NewsHeroLead v-if="featured" :article="featured" />

        <div v-if="featured && deduped.hot.length > 0" class="h-px bg-border" />

        <section v-if="deduped.hot.length > 0">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-display text-xl font-bold flex items-center gap-4">
              Top Stories
              <span class="h-px flex-1 bg-border" />
            </h2>
            <NuxtLink to="/trending" class="text-xs text-muted-foreground hover:text-foreground font-label uppercase tracking-[0.062em]">
              Trending →
            </NuxtLink>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 divide-y sm:divide-y-0">
            <div v-for="article in deduped.hot" :key="article.slug" class="py-3 first:pt-0 sm:py-0">
              <NewsCompactArticleCard :article="article" variant="default" :show-image="!!article.image_url || !!article.thumbnail_url" />
            </div>
          </div>
        </section>
      </div>

      <div class="lg:col-span-4">
        <div class="lg:sticky lg:top-[7.5rem] space-y-8">
          <NewsHeadlinesRail v-if="deduped.headlines.length > 0" :headlines="deduped.headlines" />

          <div v-if="deduped.trending.length > 0" class="border-t border-border pt-6">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-display text-sm font-bold uppercase tracking-[0.062em]">Trending Now</h3>
              <NuxtLink to="/trending" class="text-[11px] text-muted-foreground hover:text-foreground">View all</NuxtLink>
            </div>
            <div class="space-y-3">
              <NewsCompactArticleCard
                v-for="article in deduped.trending"
                :key="article.slug"
                :article="article"
                variant="minimal"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <AdSlot variant="horizontal" label="Advertisement" />

    <template v-if="deduped.sections.length > 0">
      <div class="h-px bg-border" />
      <div class="space-y-10">
        <NewsCategorySection
          v-for="section in deduped.sections"
          :key="section.slug"
          :name="section.name"
          :articles="section.articles"
          :slug="section.slug"
        />
      </div>
    </template>

    <AdSlot variant="horizontal" label="Advertisement" />
  </div>
</template>