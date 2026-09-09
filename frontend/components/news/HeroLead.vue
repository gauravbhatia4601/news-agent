<script setup lang="ts">
import type { NewsArticleDetail } from '~/types/news'

const props = defineProps<{
  article: NewsArticleDetail
}>()

function cleanExcerpt(content: string, maxLength = 220) {
  return content
    .replace(/<[^>]*>/g, ' ')
    .replace(/[#*_`>\[\]\(\)]/g, ' ')
    .replace(/&nbsp;|&#160;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;|&apos;/gi, "'")
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, maxLength)
    .trimEnd() + '...'
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
  })
}

const categoryName = computed(() => props.article.category?.name ?? '')
const categorySlug = computed(() => props.article.category?.slug ?? '')
</script>

<template>
  <section class="group">
    <NuxtLink :to="`/article/${article.slug}`" class="block">
      <div v-if="article.image_url" class="mb-4 overflow-hidden">
        <img
          :src="article.image_url"
          :alt="article.title"
          class="h-64 w-full object-cover sm:h-80 transition-transform duration-500 group-hover:scale-[1.02]"
          loading="eager"
        >
      </div>

      <div class="flex items-center gap-2 mb-2">
        <span class="font-label text-[11px] font-bold uppercase tracking-[0.062em] text-muted-foreground">{{ categoryName }}</span>
        <span class="text-border">|</span>
        <span class="font-label text-[11px] text-muted-foreground">{{ timeAgo(article.published_at) }}</span>
      </div>

      <h1 class="font-display text-2xl sm:text-3xl md:text-[2.25rem] font-bold leading-[1.15] mb-3 group-hover:underline decoration-1 underline-offset-4">
        {{ article.title }}
      </h1>

      <p class="font-serif text-sm sm:text-base leading-relaxed text-muted-foreground mb-4 max-w-3xl">
        {{ cleanExcerpt(article.content) }}
      </p>

      <div class="flex items-center gap-2 font-label text-xs text-muted-foreground">
        <span class="font-medium text-foreground">{{ article.author }}</span>
        <span>·</span>
        <span>{{ article.read_time_minutes }} min read</span>
        <span>·</span>
        <span>{{ formatDate(article.published_at) }}</span>
      </div>
    </NuxtLink>
  </section>
</template>
