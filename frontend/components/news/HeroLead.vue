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

function timeAgo(dateStr: string) {
  const diff = Date.now() - new Date(dateStr).getTime()
  const hours = Math.floor(diff / 3600000)
  if (hours < 1) return 'Just now'
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return days === 1 ? '1 day ago' : `${days} days ago`
}
</script>

<template>
  <section class="group">
    <NuxtLink :to="`/article/${article.slug}`" class="block">
      <div v-if="article.image_url" class="mb-5 overflow-hidden rounded-lg">
        <img
          :src="article.image_url"
          :alt="article.title"
          class="h-64 w-full object-cover sm:h-80 transition-transform duration-500 group-hover:scale-[1.02]"
          loading="eager"
        >
      </div>

      <div class="flex items-center gap-2 text-xs text-muted-foreground mb-3">
        <span class="font-semibold text-primary uppercase tracking-widest text-[11px]">{{ article.category }}</span>
        <span class="text-border">|</span>
        <span>{{ timeAgo(article.published_at) }}</span>
      </div>

      <h1 class="font-serif text-3xl sm:text-4xl lg:text-[2.75rem] font-bold leading-[1.15] mb-4 group-hover:underline decoration-primary/40 underline-offset-4 decoration-2">
        {{ article.title }}
      </h1>

      <p class="text-muted-foreground text-base sm:text-lg leading-relaxed mb-5 max-w-3xl">
        {{ cleanExcerpt(article.content) }}
      </p>

      <div class="flex items-center gap-3 text-sm text-muted-foreground">
        <span class="font-medium text-foreground">{{ article.author }}</span>
        <span class="text-border">·</span>
        <span>{{ article.read_time_minutes }} min read</span>
        <span class="text-border">·</span>
        <span>{{ formatDate(article.published_at) }}</span>
      </div>
    </NuxtLink>
  </section>
</template>
