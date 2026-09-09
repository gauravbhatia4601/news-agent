<script setup lang="ts">
import type { NewsArticleCard, NewsStory } from '~/types/news'

const route = useRoute()
const slug = route.params.slug as string

const api = useNewsApi()

const { data: story } = await useAsyncData<NewsStory | null>(
  `story-${slug}`,
  () => api.getStory(slug),
)

if (!story.value) {
  throw createError({ statusCode: 404, statusMessage: 'Story not found', fatal: true })
}

const page = ref(1)
const articles = ref<NewsArticleCard[]>([])
const lastPage = ref(1)
const loading = ref(false)

const { data: firstPage } = await useAsyncData(
  `story-timeline-${slug}`,
  () => api.getStoryTimeline(slug, { perPage: 9, page: 1 }),
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
    const res = await api.getStoryTimeline(slug, { perPage: 9, page: page.value })
    articles.value = [...articles.value, ...res.data]
    lastPage.value = res.meta.last_page
  } finally {
    loading.value = false
  }
}

useHead({
  title: computed(() => `${story.value?.title ?? 'Story'} — The Neural Journal`),
})
</script>

<template>
  <div v-if="story" class="space-y-8">
    <!-- Header -->
    <header class="border-b pb-6 space-y-3">
      <div class="flex items-center gap-2 font-label text-xs text-muted-foreground">
        <NuxtLink to="/" class="hover:text-foreground transition-colors">Home</NuxtLink>
        <span>/</span>
        <NuxtLink to="/stories" class="hover:text-foreground transition-colors">Stories</NuxtLink>
        <span>/</span>
        <span class="text-foreground font-medium truncate">{{ story.title }}</span>
      </div>

      <div class="flex items-center gap-3 flex-wrap">
        <NewsLiveBadge :urgency="story.urgency" />
        <span v-if="story.started_at" class="font-label text-[11px] text-muted-foreground">{{ timeAgo(story.started_at) }}</span>
        <span class="font-label text-[11px] text-muted-foreground">· {{ story.update_count }} update{{ story.update_count === 1 ? '' : 's' }}</span>
      </div>

      <h1 class="font-display text-3xl sm:text-4xl font-bold tracking-tight leading-tight">
        {{ story.title }}
      </h1>

      <p v-if="story.description" class="font-serif text-muted-foreground max-w-3xl">
        {{ story.description }}
      </p>

      <p v-if="story.urgency === 'concluded'" class="font-label text-[11px] text-muted-foreground italic">
        This story has been marked as concluded.
      </p>
    </header>

    <!-- Timeline feed -->
    <div v-if="articles.length === 0" class="py-16 text-center font-serif text-muted-foreground">
      This story has no updates yet.
    </div>

    <div v-else class="divide-y divide-border">
      <NewsCompactArticleCard
        v-for="article in articles"
        :key="article.slug"
        :article="article"
        variant="minimal"
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
  </div>
</template>