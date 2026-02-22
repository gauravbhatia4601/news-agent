<script setup lang="ts">
import type { NewsArticleCard } from '~/types/news'

defineProps<{
  headlines: NewsArticleCard[]
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
  <aside class="rounded-xl border bg-card p-5 shadow-sm">
    <h3 class="font-serif text-lg font-bold mb-5 pb-3 border-b flex items-center gap-2">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
        <path d="m18 5-3-3H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2" />
        <path d="M8 18h1" /><path d="M8 14h6" /><path d="M8 10h8" />
      </svg>
      Top Stories
    </h3>

    <ol class="space-y-0 divide-y divide-border/60">
      <li v-for="(item, i) in headlines" :key="item.slug" class="group">
        <NuxtLink :to="`/article/${item.slug}`" class="flex gap-3 py-4 first:pt-0 last:pb-0">
          <span
            class="shrink-0 flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
            :class="i === 0 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
          >
            {{ i + 1 }}
          </span>
          <div class="min-w-0 flex-1 space-y-1">
            <h4 class="text-sm font-semibold leading-snug line-clamp-2 group-hover:text-primary transition-colors">
              {{ item.title }}
            </h4>
            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
              <span class="capitalize">{{ item.category }}</span>
              <span>·</span>
              <span>{{ timeAgo(item.published_at) }}</span>
            </div>
          </div>
        </NuxtLink>
      </li>
    </ol>
  </aside>
</template>
