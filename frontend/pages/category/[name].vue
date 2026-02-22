<script setup lang="ts">
const route = useRoute()
const categoryName = route.params.name as string

const api = useNewsApi()
const articles = await api.getLatest({ category: categoryName, perPage: 18 })

useHead({
  title: `${categoryName.charAt(0).toUpperCase() + categoryName.slice(1)} News — The Daily Insight`,
})
</script>

<template>
  <div class="space-y-8">
    <header class="border-b pb-6">
      <h1 class="font-serif text-3xl sm:text-4xl font-bold tracking-tight capitalize mb-2">{{ categoryName }}</h1>
      <p class="text-muted-foreground">Latest stories in {{ categoryName }}.</p>
    </header>

    <div v-if="articles.length === 0" class="py-16 text-center text-muted-foreground">
      No articles found for this category yet.
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-8">
      <NewsCompactArticleCard
        v-for="article in articles"
        :key="article.slug"
        :article="article"
        variant="default"
        :show-image="!!article.image_url || !!article.thumbnail_url"
      />
    </div>
  </div>
</template>
