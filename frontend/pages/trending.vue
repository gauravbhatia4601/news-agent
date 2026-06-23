<script setup lang="ts">
const api = useNewsApi()

const { data: trending } = await useAsyncData('trending-page', () => api.getTrending(20), { default: () => [] as any[] })

useHead({
  title: 'Trending — The Neural Journal',
  meta: [
    { name: 'description', content: 'What\'s trending right now — the fastest-moving stories across India.' },
  ],
})
</script>

<template>
  <div class="space-y-8">
    <div class="border-b border-border pb-4">
      <h1 class="font-display text-2xl font-bold">Trending Now</h1>
      <p class="text-sm text-muted-foreground mt-1">Stories gaining the most momentum right now</p>
    </div>

    <div v-if="trending && trending.length > 0" class="space-y-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <NewsCompactArticleCard
          v-for="article in trending"
          :key="article.slug"
          :article="article"
          variant="default"
          :show-image="!!article.image_url || !!article.thumbnail_url"
        />
      </div>
    </div>

    <div v-else class="text-center py-16 text-muted-foreground">
      <p class="text-lg">No trending stories right now</p>
      <p class="text-sm mt-1">Check back soon — trending stories appear when articles gain rapid attention</p>
    </div>
  </div>
</template>
