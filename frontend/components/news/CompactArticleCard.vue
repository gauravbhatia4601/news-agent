<script setup lang="ts">
import type { NewsArticleCard } from '~/types/news'

const props = defineProps<{
  article: NewsArticleCard
  showImage?: boolean
  showExcerpt?: boolean
  variant?: 'default' | 'horizontal' | 'minimal'
}>()

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
  <!-- Horizontal variant: image left, text right -->
  <article v-if="variant === 'horizontal'" class="group flex gap-4">
    <div v-if="showImage && (article.image_url || article.thumbnail_url)" class="shrink-0 w-28 h-20 overflow-hidden rounded-md">
      <img
        :src="article.thumbnail_url || article.image_url || ''"
        :alt="article.title"
        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
        loading="lazy"
      >
    </div>
    <div class="min-w-0 flex-1 flex flex-col justify-between">
      <div>
        <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground mb-1">
          <span class="font-semibold text-primary uppercase tracking-wider">{{ article.category }}</span>
          <span>·</span>
          <span>{{ timeAgo(article.published_at) }}</span>
        </div>
        <NuxtLink :to="`/article/${article.slug}`">
          <h3 class="text-sm font-semibold leading-snug line-clamp-2 group-hover:text-primary transition-colors">
            {{ article.title }}
          </h3>
        </NuxtLink>
      </div>
      <div class="flex items-center gap-2 text-[11px] text-muted-foreground mt-1.5">
        <span>{{ article.author }}</span>
        <span>·</span>
        <span>{{ article.read_time_minutes }}m read</span>
      </div>
    </div>
  </article>

  <!-- Minimal variant: title + meta only, no card border -->
  <article v-else-if="variant === 'minimal'" class="group py-3">
    <NuxtLink :to="`/article/${article.slug}`">
      <h3 class="text-sm font-semibold leading-snug line-clamp-2 group-hover:text-primary transition-colors mb-1">
        {{ article.title }}
      </h3>
    </NuxtLink>
    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
      <span class="capitalize">{{ article.category }}</span>
      <span>·</span>
      <span>{{ timeAgo(article.published_at) }}</span>
      <span>·</span>
      <span>{{ article.read_time_minutes }}m read</span>
    </div>
  </article>

  <!-- Default variant: vertical card -->
  <article v-else class="group flex flex-col">
    <NuxtLink v-if="showImage && (article.image_url || article.thumbnail_url)" :to="`/article/${article.slug}`" class="mb-3 block overflow-hidden rounded-lg">
      <img
        :src="article.thumbnail_url || article.image_url || ''"
        :alt="article.title"
        class="aspect-[16/10] w-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
        loading="lazy"
      >
    </NuxtLink>

    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground mb-2">
      <span class="font-semibold text-primary uppercase tracking-wider">{{ article.category }}</span>
      <span>·</span>
      <span>{{ timeAgo(article.published_at) }}</span>
    </div>

    <NuxtLink :to="`/article/${article.slug}`" class="block flex-1">
      <h3 class="font-serif text-lg font-bold leading-snug line-clamp-3 group-hover:underline decoration-primary/40 underline-offset-4 decoration-1 mb-2">
        {{ article.title }}
      </h3>
    </NuxtLink>

    <div class="flex items-center gap-2 text-xs text-muted-foreground mt-auto pt-2">
      <span class="truncate">{{ article.author }}</span>
      <span class="text-border">·</span>
      <span class="whitespace-nowrap">{{ article.read_time_minutes }} min read</span>
    </div>
  </article>
</template>
