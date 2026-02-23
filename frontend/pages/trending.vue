<script setup lang="ts">
const api = useNewsApi()
const trending = await api.getPopular({ perPage: 18 })

useHead({ title: 'Trending Stories — The Trust Journal' })
</script>

<template>
  <div class="space-y-8">
    <header class="border-b pb-6">
      <h1 class="font-serif text-3xl sm:text-4xl font-bold tracking-tight mb-2">Trending Stories</h1>
      <p class="text-muted-foreground">Most read stories right now, curated by real reader interest.</p>
    </header>

    <div v-if="trending.length === 0" class="py-16 text-center text-muted-foreground">
      No trending stories available yet.
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-8">
      <NewsCompactArticleCard
        v-for="item in trending"
        :key="item.slug"
        :article="item"
        variant="default"
        :show-image="!!item.image_url || !!item.thumbnail_url"
      />
    </div>
  </div>
</template>
