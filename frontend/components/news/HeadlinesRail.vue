<script setup lang="ts">
import type { NewsArticleCard } from '~/types/news'

defineProps<{
  headlines: NewsArticleCard[]
}>()
</script>

<template>
  <aside>
    <h3 class="font-display text-xl font-bold mb-4 pb-2 border-b-strong">
      Most Popular News
    </h3>

    <ol class="space-y-0 divide-y divide-border">
      <li v-for="(item, i) in headlines" :key="item.slug" class="group">
        <NuxtLink :to="`/article/${item.slug}`" class="flex gap-3 py-4 first:pt-0 last:pb-0">
          <span
            class="shrink-0 flex items-center justify-center w-6 h-6 font-label text-xs font-bold"
            :class="i === 0 ? 'bg-foreground text-background' : 'text-muted-foreground'"
          >
            {{ i + 1 }}
          </span>
          <div class="min-w-0 flex-1 space-y-1">
            <h4 class="font-display text-sm font-bold leading-snug line-clamp-2 group-hover:underline decoration-1 underline-offset-2">
              {{ item.title }}
            </h4>
            <div class="flex items-center gap-1.5 font-label text-[11px] text-muted-foreground">
              <span class="capitalize">{{ item.category?.name ?? '' }}</span>
              <span>·</span>
              <span>{{ timeAgo(item.published_at) }}</span>
            </div>
          </div>
        </NuxtLink>
      </li>
    </ol>
  </aside>
</template>
