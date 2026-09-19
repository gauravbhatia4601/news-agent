<script setup lang="ts">
const api = useNewsApi()

const { data: trending } = await useAsyncData('trending-page', () => api.getTrending(20), { default: () => [] as any[] })
// Low-traffic periods leave nothing above the momentum floor — fall back to latest
// with an honest label instead of a dead-end page.
const { data: fallbackLatest } = await useAsyncData('trending-fallback', () => api.getLatest({ perPage: 20 }), {
  default: () => [] as any[],
})

const showMomentum = computed(() => (trending.value?.length ?? 0) > 0)
const displayArticles = computed(() => (showMomentum.value ? trending.value : (fallbackLatest.value ?? [])))

useHead({
  title: 'Trending',
  meta: [
    { name: 'description', content: 'What\'s trending right now — the fastest-moving stories across India and the world, ranked live by reader momentum.' },
  ],
  link: [
    { rel: 'canonical', href: useCanonical() },
  ],
})
usePageSeo('Trending', 'What\'s trending right now — the fastest-moving stories across India and the world, ranked live by reader momentum.')
</script>

<template>
  <div class="space-y-8">
    <div class="border-b border-border pb-4">
      <h1 class="font-display text-2xl font-bold">Trending Now</h1>
      <p class="text-sm text-muted-foreground mt-1">{{ showMomentum ? 'Stories gaining the most momentum right now' : 'Nothing is surging right now — here are the latest stories' }}</p>
    </div>

    <div v-if="displayArticles.length > 0" class="space-y-6">
      <!-- h2 bridges h1 -> h3 card headings (heading-order-skip in the OpenSEO audit) -->
      <h2 class="sr-only">Fastest moving stories</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <NewsCompactArticleCard
          v-for="article in displayArticles"
          :key="article.slug"
          :article="article"
          variant="default"
          :show-image="!!article.image_url || !!article.thumbnail_url"
        />
      </div>
    </div>

    <div v-else class="text-center py-16 text-muted-foreground">
      <p class="text-lg">No stories yet</p>
      <p class="text-sm mt-1">Check back soon — trending stories appear when articles gain rapid attention</p>
    </div>
  </div>
</template>
