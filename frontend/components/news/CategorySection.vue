<script setup lang="ts">
import type { NewsArticleCard } from '~/types/news'

defineProps<{
  name: string
  articles: NewsArticleCard[]
}>()
</script>

<template>
  <section>
    <!-- Section header with divider -->
    <div class="flex items-center gap-4 mb-5">
      <h2 class="font-serif text-xl font-bold capitalize shrink-0">{{ name }}</h2>
      <span class="h-px flex-1 bg-border" />
      <NuxtLink
        :to="`/category/${name}`"
        class="shrink-0 text-xs font-medium text-primary hover:underline underline-offset-4 uppercase tracking-wider"
      >
        View all →
      </NuxtLink>
    </div>

    <!-- Empty state -->
    <div v-if="articles.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
      No fresh stories yet in this category.
    </div>

    <!-- Articles grid: first article larger, rest compact -->
    <div v-else class="grid grid-cols-1 md:grid-cols-12 gap-6">
      <!-- Lead article for category (left, larger) -->
      <div class="md:col-span-5">
        <article class="group">
          <NuxtLink v-if="articles[0].image_url || articles[0].thumbnail_url" :to="`/article/${articles[0].slug}`" class="mb-3 block overflow-hidden rounded-lg">
            <img
              :src="articles[0].thumbnail_url || articles[0].image_url || ''"
              :alt="articles[0].title"
              class="aspect-[16/10] w-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
              loading="lazy"
            >
          </NuxtLink>
          <NuxtLink :to="`/article/${articles[0].slug}`">
            <h3 class="font-serif text-xl font-bold leading-snug line-clamp-3 group-hover:underline decoration-primary/40 underline-offset-4 decoration-1 mb-2">
              {{ articles[0].title }}
            </h3>
          </NuxtLink>
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <span>{{ articles[0].author }}</span>
            <span class="text-border">·</span>
            <span>{{ articles[0].read_time_minutes }} min read</span>
          </div>
        </article>
      </div>

      <!-- Remaining articles (right, stacked list) -->
      <div v-if="articles.length > 1" class="md:col-span-7">
        <div class="divide-y divide-border/60">
          <div v-for="article in articles.slice(1)" :key="article.slug" class="py-3 first:pt-0 last:pb-0">
            <NewsCompactArticleCard :article="article" variant="horizontal" :show-image="!!article.image_url || !!article.thumbnail_url" />
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
