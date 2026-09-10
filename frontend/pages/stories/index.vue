<script setup lang="ts">
import type { NewsStory } from '~/types/news'

const api = useNewsApi()

const { data: stories } = await useAsyncData(
  'stories-index',
  () => api.getStories({ perPage: 24 }),
  { default: () => ({ data: [] as NewsStory[], meta: { current_page: 1, last_page: 1, total: 0 } }) },
)

useSeoMeta({
  description: 'Live minute-by-minute timelines of breaking news events.',
})

// Hydration-safe: formatAbsolute (deterministic UTC) during SSR + initial client
// render, timeAgo only after mount. Composables can't be called per v-for item,
// so we use the same mechanism manually.
const isMounted = ref(false)
onMounted(() => { isMounted.value = true })
function storyTime(dateStr?: string): string {
  if (!dateStr) return ''
  return isMounted.value ? timeAgo(dateStr) : formatAbsolute(dateStr)
}

useHead({
  title: 'Live Stories',
  link: [
    { rel: 'canonical', href: useCanonical() },
  ],
})
</script>

<template>
  <div class="space-y-8">
    <header class="border-b pb-6">
      <div class="flex items-center gap-2 font-label text-xs text-muted-foreground mb-3">
        <NuxtLink to="/" class="hover:text-foreground transition-colors">Home</NuxtLink>
        <span>/</span>
        <span class="text-foreground font-medium">Stories</span>
      </div>
      <h1 class="font-display text-3xl sm:text-4xl font-bold tracking-tight mb-2">
        Live Stories
      </h1>
      <p class="font-serif text-muted-foreground">
        Ongoing events and developing news, tracked in real time.
      </p>
    </header>

    <div v-if="!stories?.data?.length" class="py-16 text-center font-serif text-muted-foreground">
      No active stories right now.
    </div>

    <div v-else class="space-y-4">
      <NuxtLink
        v-for="story in stories.data"
        :key="story.slug"
        :to="`/story/${story.slug}`"
        class="block group border border-border rounded-none p-5 hover:border-foreground transition-colors"
      >
        <div class="flex items-center gap-3 mb-2 flex-wrap">
          <NewsLiveBadge :urgency="story.urgency" />
          <span v-if="story.started_at" class="font-label text-[11px] text-muted-foreground">{{ storyTime(story.started_at) }}</span>
          <span class="font-label text-[11px] text-muted-foreground">· {{ story.update_count }} update{{ story.update_count === 1 ? '' : 's' }}</span>
        </div>
        <h2 class="font-display text-xl font-bold leading-snug group-hover:underline decoration-1 underline-offset-4 mb-1">
          {{ story.title }}
        </h2>
        <p v-if="story.description" class="font-serif text-sm text-muted-foreground line-clamp-2">
          {{ story.description }}
        </p>
      </NuxtLink>
    </div>
  </div>
</template>