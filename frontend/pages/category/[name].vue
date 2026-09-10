<script setup lang="ts">
import type { CategoryNode, NewsArticleCard } from '~/types/news'

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

const page = ref(1)
const articles = ref<NewsArticleCard[]>([])
const lastPage = ref(1)
const loading = ref(false)

const { data: firstPage } = await useAsyncData(
  `category-${categorySlug}`,
  () => api.getLatestPaginated({ category: categorySlug, perPage: 9, page: 1 }),
)

if (firstPage.value) {
  articles.value = firstPage.value.data
  lastPage.value = firstPage.value.meta.last_page
}

const canLoadMore = computed(() => page.value < lastPage.value)

async function loadMore() {
  if (loading.value || !canLoadMore.value) return
  loading.value = true
  page.value++
  try {
    const res = await api.getLatestPaginated({ category: categorySlug, perPage: 9, page: page.value })
    articles.value = [...articles.value, ...res.data]
    lastPage.value = res.meta.last_page
  } finally {
    loading.value = false
  }
}

useHead({
  title: computed(() => `${categoryName.value} News`),
  meta: [
    { name: 'description', content: computed(() => `Latest news, analysis and updates about ${categoryName.value} — curated by The Neural Journal.`) },
  ],
  link: [
    { rel: 'canonical', href: useCanonical() },
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
        <span class="text-foreground font-medium capitalize">{{ categoryName }}</span>
      </div>
      <h1 class="font-display text-3xl sm:text-4xl font-bold tracking-tight capitalize mb-2">
        {{ categoryName }}
      </h1>
      <p class="font-serif text-muted-foreground">
        Latest stories in {{ categoryName }}.
      </p>
    </header>

    <div v-if="articles.length === 0" class="py-16 text-center font-serif text-muted-foreground">
      No articles found for this category yet.
    </div>

    <template v-else>
      <!-- Hero lead (first article) — inline, uses card excerpt (no content field on cards) -->
      <section v-if="articles[0]" class="group">
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
            <span class="font-label text-[11px] text-muted-foreground">{{ timeAgo(articles[0].published_at) }}</span>
            <span v-if="isNew(articles[0].published_at)" class="font-label text-[10px] font-bold uppercase tracking-[0.062em] text-secondary">New</span>
          </div>

          <h2 class="font-display text-2xl sm:text-3xl md:text-[2.25rem] font-bold leading-[1.15] mb-3 group-hover:underline decoration-1 underline-offset-4">
            {{ articles[0].title }}
          </h2>

          <p v-if="articles[0].excerpt" class="font-serif text-sm sm:text-base leading-relaxed text-muted-foreground mb-4 max-w-3xl">
            {{ articles[0].excerpt }}
          </p>

          <div class="flex items-center gap-2 font-label text-xs text-muted-foreground">
            <span class="font-medium text-foreground">{{ articles[0].author }}</span>
            <span>·</span>
            <span>{{ articles[0].read_time_minutes }} min read</span>
          </div>
        </NuxtLink>
      </section>

      <!-- Grid for the rest -->
      <div v-if="articles.length > 1" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-8">
        <NewsCompactArticleCard
          v-for="article in articles.slice(1)"
          :key="article.slug"
          :article="article"
          variant="default"
          :show-image="!!article.image_url || !!article.thumbnail_url"
        />
      </div>

      <!-- Load more -->
      <div v-if="canLoadMore" class="flex justify-center pt-4">
        <button
          :disabled="loading"
          class="border border-border px-6 py-3 font-label text-xs font-bold uppercase tracking-[0.062em] text-muted-foreground hover:text-foreground hover:border-foreground transition-colors disabled:opacity-40"
          @click="loadMore"
        >
          {{ loading ? 'Loading…' : 'Load more' }}
        </button>
      </div>
    </template>
  </div>
</template>