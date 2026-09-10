<script setup lang="ts">
import type { NewsStory } from '~/types/news'

const api = useNewsApi()

// Over-fetch by 1 so dedup can drop collisions without leaving sections thin.
const { data: featured } = await useAsyncData('featured', () => api.getFeatured())
const { data: hot } = await useAsyncData('hot', () => api.getHot({ perPage: 7 }), { default: () => [] as any[] })
const { data: categoryTree } = await useAsyncData('home-category-tree', () => api.getCategoryTree(), { default: () => [] as any[] })

const { data: liveStories } = await useAsyncData<{ data: NewsStory[]; meta: { current_page: number; last_page: number; total: number } }>(
  'home-live-stories',
  () => api.getStories({ perPage: 5 }),
  { default: () => ({ data: [] as NewsStory[], meta: { current_page: 1, last_page: 1, total: 0 } }) },
)

const hasLiveStories = computed(() => (liveStories.value?.data?.length ?? 0) > 0)

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

// Priority dedup: featured → hot → category sections.
const deduped = computed(() => {
  const seen = new Set<string>()
  if (featured.value?.slug) seen.add(featured.value.slug)

  const hotD = dedupeBySlug(hot.value ?? [], seen).slice(0, 6)

  const sections = (categorySections.value ?? [])
    .map(section => ({
      name: section.name,
      slug: section.slug,
      articles: dedupeBySlug(section.articles ?? [], seen).slice(0, 4),
    }))
    .filter(section => section.articles.length > 0)

  return { hot: hotD, sections }
})

useHead({
  title: 'Latest News from India',
  meta: [
    { name: 'description', content: 'Stay informed with the latest news across India — politics, business, technology, sports, entertainment and more.' },
  ],
  link: [
    { rel: 'canonical', href: useCanonical() },
  ],
})
</script>

<template>
  <div class="space-y-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
      <div :class="hasLiveStories ? 'lg:col-span-8' : 'lg:col-span-12'" class="space-y-10">
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

      <div v-if="hasLiveStories" class="lg:col-span-4">
        <div class="lg:sticky lg:top-[7.5rem] space-y-8">
          <section class="border-t border-border pt-6">
            <div class="flex items-center gap-2 mb-4">
              <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500">
                <span class="absolute inset-0 rounded-full bg-red-500 animate-ping" />
              </span>
              <h3 class="font-display text-sm font-bold uppercase tracking-[0.062em]">Live</h3>
            </div>

            <div class="space-y-5">
              <NuxtLink
                v-for="s in liveStories!.data"
                :key="s.slug"
                :to="`/story/${s.slug}`"
                class="block group"
              >
                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                  <NewsLiveBadge :urgency="s.urgency" />
                  <span class="font-label text-[11px] text-muted-foreground">· {{ s.update_count }} update{{ s.update_count === 1 ? '' : 's' }}</span>
                </div>
                <h4 class="font-serif text-sm font-bold leading-snug line-clamp-2 group-hover:underline decoration-1 underline-offset-2 mb-1">
                  {{ s.title }}
                </h4>
                <p v-if="s.latest_update" class="text-[11px] text-muted-foreground line-clamp-1">
                  {{ s.latest_update.content }}
                </p>
              </NuxtLink>
            </div>
          </section>
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