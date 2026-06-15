<script setup lang="ts">
const api = useNewsApi()

const { data: featured } = await useAsyncData('featured', () => api.getFeatured())
const { data: headlines } = await useAsyncData('headlines', () => api.getHeadlines(6), { default: () => [] as any[] })
const { data: hot } = await useAsyncData('hot', () => api.getHot({ perPage: 6 }), { default: () => [] as any[] })
const { data: trending } = await useAsyncData('trending-home', () => api.getTrending(4), { default: () => [] as any[] })
const { data: categoryTree } = await useAsyncData('home-category-tree', () => api.getCategoryTree(), { default: () => [] as any[] })

const categorySections = ref<{ name: string; slug: string; articles: any[] }[]>([])

watchEffect(async () => {
  if (!categoryTree.value?.length) {
    categorySections.value = []
    return
  }
  const results = await Promise.all(
    categoryTree.value.map(async (cat: any) => ({
      name: cat.name,
      slug: cat.slug,
      articles: await api.getHot({ category: cat.slug, perPage: 4 }),
    }))
  )
  categorySections.value = results
})

useHead({
  title: 'The AI Journal — Latest News from India',
  meta: [
    { name: 'description', content: 'Stay informed with the latest news across India — politics, business, technology, sports, entertainment and more.' },
  ],
})
</script>

<template>
  <div class="space-y-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
      <div class="lg:col-span-8 space-y-10">
        <NewsHeroLead v-if="featured" :article="featured" />

        <div v-if="featured && hot && hot.length > 0" class="h-px bg-border" />

        <section v-if="hot && hot.length > 0">
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
            <div v-for="article in hot" :key="article.slug" class="py-3 first:pt-0 sm:py-0">
              <NewsCompactArticleCard :article="article" variant="default" :show-image="!!article.image_url || !!article.thumbnail_url" />
            </div>
          </div>
        </section>
      </div>

      <div class="lg:col-span-4">
        <div class="lg:sticky lg:top-[7.5rem] space-y-8">
          <NewsHeadlinesRail v-if="headlines && headlines.length > 0" :headlines="headlines" />

          <div v-if="trending && trending.length > 0" class="border-t border-border pt-6">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-display text-sm font-bold uppercase tracking-[0.062em]">Trending Now</h3>
              <NuxtLink to="/trending" class="text-[11px] text-muted-foreground hover:text-foreground">View all</NuxtLink>
            </div>
            <div class="space-y-3">
              <NewsCompactArticleCard
                v-for="article in trending"
                :key="article.slug"
                :article="article"
                variant="minimal"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <template v-if="categorySections.length > 0">
      <div class="h-px bg-border" />
      <div class="space-y-10">
        <NewsCategorySection
          v-for="section in categorySections"
          :key="section.slug"
          :name="section.name"
          :articles="section.articles"
          :slug="section.slug"
        />
      </div>
    </template>
  </div>
</template>
