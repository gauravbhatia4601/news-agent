<script setup lang="ts">
import type { NewsArticleCard, NewsStory, StoryTimelineEntry } from '~/types/news'

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

// ── Timeline (left column) ──
const timelinePage = ref(1)
const timeline = ref<StoryTimelineEntry[]>([])
const timelineLastPage = ref(1)
const timelineLoading = ref(false)

const { data: firstTimeline } = await useAsyncData(
  `story-timeline-${slug}`,
  () => api.getStoryTimeline(slug, { perPage: 9, page: 1 }),
)

if (firstTimeline.value) {
  timeline.value = firstTimeline.value.data
  timelineLastPage.value = firstTimeline.value.meta.last_page
}

const canLoadMoreTimeline = computed(() => timelinePage.value < timelineLastPage.value)
const newestEntryId = computed(() => timeline.value[0]?.id)

async function loadMoreTimeline() {
  if (timelineLoading.value || !canLoadMoreTimeline.value) return
  timelineLoading.value = true
  timelinePage.value++
  try {
    const res = await api.getStoryTimeline(slug, { perPage: 9, page: timelinePage.value })
    timeline.value = [...timeline.value, ...res.data]
    timelineLastPage.value = res.meta.last_page
  } finally {
    timelineLoading.value = false
  }
}

// ── Supporting articles (right column) ──
const articlesPage = ref(1)
const articles = ref<NewsArticleCard[]>([])
const articlesLastPage = ref(1)
const articlesLoading = ref(false)

const { data: firstArticles } = await useAsyncData(
  `story-articles-${slug}`,
  () => api.getStoryArticles(slug, { perPage: 8, page: 1 }),
)

if (firstArticles.value) {
  articles.value = firstArticles.value.data
  articlesLastPage.value = firstArticles.value.meta.last_page
}

const canLoadMoreArticles = computed(() => articlesPage.value < articlesLastPage.value)

async function loadMoreArticles() {
  if (articlesLoading.value || !canLoadMoreArticles.value) return
  articlesLoading.value = true
  articlesPage.value++
  try {
    const res = await api.getStoryArticles(slug, { perPage: 8, page: articlesPage.value })
    articles.value = [...articles.value, ...res.data]
    articlesLastPage.value = res.meta.last_page
  } finally {
    articlesLoading.value = false
  }
}

// "Sep 9, 3:10 PM" — long-form time label for timeline entries.
// Parses the ISO string and formats UTC parts directly (no Date methods that
// shift by timezone) so server and client produce the identical string.
function formatTime(dateStr: string): string {
  const m = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/)
  if (!m) return dateStr
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const hour = Number(m[4])
  const h12 = hour % 12 || 12
  const ampm = hour >= 12 ? 'PM' : 'AM'
  return `${months[Number(m[2]) - 1]} ${Number(m[3])}, ${h12}:${m[5]} ${ampm}`
}

const siteUrl = useRuntimeConfig().public.siteUrl
const canonicalUrl = computed(() => `${siteUrl}/story/${slug}`)

const startedRel = useRelativeTime(() => story.value?.started_at)

const seoDescription = computed(() => {
  const s = story.value
  if (!s) return 'Live coverage and timeline of a developing news story.'
  if (s.description) return s.description
  if (s.latest_update?.content) {
    return s.latest_update.content.length > 155
      ? s.latest_update.content.slice(0, 155).trimEnd() + '…'
      : s.latest_update.content
  }
  return 'Live coverage and timeline of a developing news story.'
})

const dateModified = computed(() => {
  const s = story.value
  return s?.latest_update?.event_at || s?.started_at || ''
})

useSeoMeta({
  title: () => story.value?.title ?? 'Story',
  description: seoDescription,
  ogTitle: () => story.value?.title ?? 'Story',
  ogDescription: seoDescription,
  ogUrl: canonicalUrl,
  twitterCard: 'summary_large_image',
})

useHead({
  link: [
    { rel: 'canonical', href: canonicalUrl },
  ],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: () => JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'LiveBlogPosting',
        headline: story.value?.title || '',
        datePublished: story.value?.started_at || '',
        dateModified: dateModified.value,
        mainEntityOfPage: {
          '@type': 'WebPage',
          '@id': canonicalUrl.value,
        },
        coverageStartTime: story.value?.started_at || '',
        liveBlogUpdate: (timeline.value ?? [])
          .slice(0, 20)
          .filter((entry: StoryTimelineEntry) => entry.content?.trim())
          .map((entry: StoryTimelineEntry) => ({
            '@type': 'BlogPosting',
            headline: entry.content,
            datePublished: entry.event_at,
          })),
      }),
    },
  ],
})
</script>

<template>
  <div v-if="story" class="space-y-8">
    <!-- Header (full width) -->
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
        <span v-if="story.started_at" class="font-label text-[11px] text-muted-foreground">{{ startedRel }}</span>
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

    <!-- Two-column body -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
      <!-- LEFT: Timeline -->
      <section class="lg:col-span-7">
        <h2 class="font-display text-xl font-bold mb-6">Timeline</h2>

        <div v-if="timeline.length === 0" class="py-16 text-center font-serif text-muted-foreground">
          This story has no updates yet.
        </div>

        <ol v-else class="relative border-l border-border pl-4 space-y-6">
          <li
            v-for="entry in timeline"
            :key="entry.id"
            class="relative"
          >
            <!-- Bullet marker -->
            <span
              class="absolute -left-[1.375rem] top-1.5 h-2 w-2 rounded-full bg-foreground ring-2 ring-background"
              aria-hidden="true"
            />

            <div class="flex items-center gap-2 mb-1">
              <time class="font-label text-[11px] text-muted-foreground" :datetime="entry.event_at">
                {{ formatTime(entry.event_at) }}
              </time>
              <span
                v-if="entry.id === newestEntryId && isNew(entry.event_at)"
                class="font-label text-[10px] font-bold uppercase tracking-[0.062em] text-secondary"
              >New</span>
            </div>

            <p class="font-serif text-sm leading-relaxed">{{ entry.content }}</p>

            <a
              v-if="entry.source_url"
              :href="entry.source_url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-block mt-1 font-label text-[11px] text-muted-foreground hover:text-foreground transition-colors"
            >
              {{ entry.source_name || 'Source' }}
            </a>
            <span v-else-if="entry.source_name" class="block mt-1 font-label text-[11px] text-muted-foreground">
              {{ entry.source_name }}
            </span>
          </li>
        </ol>

        <div v-if="canLoadMoreTimeline" class="flex justify-center pt-8">
          <button
            :disabled="timelineLoading"
            class="border border-border px-6 py-3 font-label text-xs font-bold uppercase tracking-[0.062em] text-muted-foreground hover:text-foreground hover:border-foreground transition-colors disabled:opacity-40"
            @click="loadMoreTimeline"
          >
            {{ timelineLoading ? 'Loading…' : 'Load more' }}
          </button>
        </div>
      </section>

      <!-- RIGHT: Supporting articles -->
      <aside class="lg:col-span-5">
        <div class="lg:sticky lg:top-[7.5rem]">
          <h2 class="font-display text-xl font-bold mb-4 pb-2 border-b">Supporting Articles</h2>

          <div v-if="articles.length === 0" class="font-label text-xs text-muted-foreground">
            No supporting articles yet.
          </div>

          <div v-else class="divide-y divide-border">
            <NewsCompactArticleCard
              v-for="article in articles"
              :key="article.slug"
              :article="article"
              variant="minimal"
            />
          </div>

          <div v-if="canLoadMoreArticles" class="flex justify-center pt-6">
            <button
              :disabled="articlesLoading"
              class="border border-border px-5 py-2.5 font-label text-xs font-bold uppercase tracking-[0.062em] text-muted-foreground hover:text-foreground hover:border-foreground transition-colors disabled:opacity-40"
              @click="loadMoreArticles"
            >
              {{ articlesLoading ? 'Loading…' : 'Load more' }}
            </button>
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>