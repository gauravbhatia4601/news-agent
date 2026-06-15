<script setup lang="ts">
import type { CategoryNode } from '~/types/news'

const route = useRoute()
const categorySlug = route.params.name as string

const api = useNewsApi()

const { data: categoryTree } = await useAsyncData(
  `category-tree-${categorySlug}`,
  () => api.getCategoryTree(),
  { default: () => [] as CategoryNode[] }
)

const categoryName = computed(() => {
  const flat = (categoryTree.value ?? []).flatMap((c: CategoryNode) => [c, ...(c.children ?? [])])
  const match = flat.find((c) => c.slug === categorySlug)
  return match?.name ?? categorySlug
})

const { data: articles } = await useAsyncData(
  `category-${categorySlug}`,
  () => api.getLatest({ category: categorySlug, perPage: 18 }),
  { default: () => [] as any[] }
)

useHead({
  title: computed(() => `${categoryName.value} News — The AI Journal`),
})
</script>

<template>
  <div class="space-y-8">
    <!-- Breadcrumb + header -->
    <header class="border-b pb-6">
      <div class="flex items-center gap-2 font-label text-xs text-muted-foreground mb-3">
        <NuxtLink to="/" class="hover:text-foreground transition-colors">Home</NuxtLink>
        <span>/</span>
        <span class="text-foreground font-medium capitalize">{{ categoryName }}</span>
      </div>
      <h1 class="font-display text-3xl sm:text-4xl font-bold tracking-tight capitalize mb-2">
        {{ categoryName }}
      </h1>
      <p class="font-serif text-muted-foreground">
        Latest stories in {{ categoryName }}.
      </p>
    </header>

    <div v-if="articles && articles.length === 0" class="py-16 text-center font-serif text-muted-foreground">
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
