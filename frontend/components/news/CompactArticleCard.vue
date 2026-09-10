<script setup lang="ts">
import type { NewsArticleCard } from '~/types/news'

const props = defineProps<{
  article: NewsArticleCard
  showImage?: boolean
  showExcerpt?: boolean
  variant?: 'default' | 'horizontal' | 'minimal'
}>()

const categoryName = computed(() => props.article.category?.name ?? '')
const categorySlug = computed(() => props.article.category?.slug ?? '')
const locationName = computed(() => props.article.location?.name ?? '')
</script>

<template>
  <article v-if="variant === 'horizontal'" class="group flex gap-3">
    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-1.5 mb-0.5">
        <span class="font-label text-[11px] font-bold uppercase tracking-[0.062em] text-muted-foreground">{{ categoryName }}</span>
        <span class="text-border">·</span>
        <span class="font-label text-[11px] text-muted-foreground">{{ timeAgo(article.published_at) }}</span>
        <span v-if="isNew(article.published_at)" class="font-label text-[10px] font-bold uppercase tracking-[0.062em] text-secondary">New</span>
      </div>
      <NuxtLink :to="`/article/${article.slug}`">
        <h3 class="font-display text-sm font-bold leading-snug line-clamp-2 group-hover:underline decoration-1 underline-offset-2">
          {{ article.title }}
        </h3>
      </NuxtLink>
      <p v-if="article.excerpt" class="text-xs text-muted-foreground leading-relaxed mt-1 line-clamp-2">{{ article.excerpt }}</p>
      <div class="flex items-center gap-1.5 mt-1 font-label text-[11px] text-muted-foreground">
        <span>{{ article.author }}</span>
        <span>·</span>
        <span>{{ article.read_time_minutes }}m read</span>
      </div>
    </div>
    <div v-if="showImage && (article.image_url || article.thumbnail_url)" class="shrink-0 w-[74px] h-[74px] overflow-hidden">
      <img
        :src="article.thumbnail_url || article.image_url || ''"
        :alt="article.title"
        width="800"
        height="450"
        class="h-full w-full object-cover"
        loading="lazy"
      >
    </div>
  </article>

  <article v-else-if="variant === 'minimal'" class="group py-3">
    <NuxtLink :to="`/article/${article.slug}`">
      <h3 class="font-display text-sm font-bold leading-snug line-clamp-2 group-hover:underline decoration-1 underline-offset-2 mb-1">
        {{ article.title }}
      </h3>
    </NuxtLink>
    <p v-if="article.excerpt" class="text-xs text-muted-foreground leading-relaxed line-clamp-2 mb-1">{{ article.excerpt }}</p>
    <div class="flex items-center gap-1.5 font-label text-[11px] text-muted-foreground">
      <span class="capitalize">{{ categoryName }}</span>
      <span>·</span>
      <span>{{ timeAgo(article.published_at) }}</span>
      <span v-if="isNew(article.published_at)" class="font-label text-[10px] font-bold uppercase tracking-[0.062em] text-secondary">New</span>
      <span>·</span>
      <span>{{ article.read_time_minutes }}m read</span>
    </div>
  </article>

  <article v-else class="group flex flex-col">
    <NuxtLink v-if="showImage && (article.image_url || article.thumbnail_url)" :to="`/article/${article.slug}`" class="mb-3 block overflow-hidden">
      <img
        :src="article.thumbnail_url || article.image_url || ''"
        :alt="article.title"
        width="800"
        height="450"
        class="aspect-[16/10] w-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
        loading="lazy"
      >
    </NuxtLink>

    <div class="flex items-center gap-1.5 mb-1.5">
      <span class="font-label text-[11px] font-bold uppercase tracking-[0.062em] text-muted-foreground">{{ categoryName }}</span>
      <span class="text-border">·</span>
      <span class="font-label text-[11px] text-muted-foreground">{{ timeAgo(article.published_at) }}</span>
      <span v-if="isNew(article.published_at)" class="font-label text-[10px] font-bold uppercase tracking-[0.062em] text-secondary">New</span>
    </div>

    <NuxtLink :to="`/article/${article.slug}`" class="block flex-1">
        <h3 class="font-display text-lg font-bold leading-snug line-clamp-3 group-hover:underline decoration-1 underline-offset-4 mb-2">
          {{ article.title }}
        </h3>
      </NuxtLink>

      <p v-if="article.excerpt" class="text-xs text-muted-foreground leading-relaxed line-clamp-2 mb-2">{{ article.excerpt }}</p>

      <div class="flex items-center gap-2 font-label text-xs text-muted-foreground mt-auto pt-2">
      <span class="truncate">{{ article.author }}</span>
      <span>·</span>
      <span class="whitespace-nowrap">{{ article.read_time_minutes }} min read</span>
    </div>
  </article>
</template>
