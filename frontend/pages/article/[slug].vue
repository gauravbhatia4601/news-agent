<script setup lang="ts">
const route = useRoute()
const articleSlug = route.params.slug as string

const api = useNewsApi()
const article = await api.getArticle(articleSlug)
const related = await api.getRelated(articleSlug)

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
  })
}

useSeoMeta({
  title: () => article ? (article.meta_title || article.title) : 'Article Not Found',
  description: () => article ? (article.meta_description || article.title) : '',
  keywords: () => article?.meta_keywords || '',
  ogTitle: () => article ? (article.meta_title || article.title) : '',
  ogDescription: () => article ? (article.meta_description || article.title) : '',
  twitterTitle: () => article ? (article.meta_title || article.title) : '',
  twitterDescription: () => article ? (article.meta_description || article.title) : '',
})
</script>

<template>
  <div v-if="!article" class="py-20 text-center">
    <h1 class="text-3xl font-serif font-bold mb-4">Article Not Found</h1>
    <p class="text-muted-foreground mb-8">We couldn't find the article you were looking for.</p>
    <NuxtLink to="/" class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-8 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50">Return Home</NuxtLink>
  </div>

  <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-12">
    <!-- Main Article Content -->
    <article class="lg:col-span-8 space-y-8">
      <header class="space-y-6 border-b pb-8">
        <div class="flex items-center gap-x-2 text-sm text-primary font-medium">
          <NuxtLink :to="`/category/${article.category}`" class="uppercase hover:underline underline-offset-4">{{ article.category }}</NuxtLink>
        </div>
        
        <h1 class="font-serif text-3xl sm:text-4xl md:text-5xl font-bold leading-tight tracking-tight">{{ article.title }}</h1>
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-sm text-muted-foreground bg-muted/30 p-4 rounded-lg">
          <div class="flex items-center gap-2">
            <span class="font-medium text-foreground">By {{ article.author }}</span>
            <span>•</span>
            <span>{{ formatDate(article.published_at) }}</span>
          </div>
          <div class="flex items-center gap-4">
            <span class="flex items-center gap-1">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              {{ article.read_time_minutes }} min read
            </span>
            <span class="flex items-center gap-1">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              {{ article.views }} views
            </span>
          </div>
        </div>

        <div v-if="article.image_url" class="overflow-hidden rounded-xl border">
          <img
            :src="article.image_url"
            :alt="article.title"
            class="h-64 w-full object-cover md:h-80"
            loading="lazy"
          >
        </div>
      </header>

      <div class="prose prose-lg max-w-none prose-headings:font-serif prose-headings:text-primary prose-a:text-primary hover:prose-a:text-primary/80 prose-p:leading-relaxed prose-p:text-gray-800 dark:prose-p:text-gray-200">
        <div v-html="article.content"></div>
      </div>

      <!-- Sources & Citations -->
      <footer v-if="article.sources?.length" class="mt-12 pt-8 border-t">
        <h3 class="font-serif text-xl font-bold mb-4">Sources & References</h3>
        <ul class="space-y-3">
          <li v-for="source in article.sources" :key="source.url" class="text-sm border rounded-lg p-4 bg-muted/20">
            <a :href="source.url" target="_blank" rel="noopener noreferrer" class="font-medium hover:underline text-primary group flex items-start gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0 opacity-50 group-hover:opacity-100"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
              <span>{{ source.name }}</span>
            </a>
          </li>
        </ul>
      </footer>
    </article>

    <!-- Sidebar / Read Next -->
    <aside class="lg:col-span-4">
      <div class="lg:sticky lg:top-[7.5rem] rounded-xl border bg-card p-5 shadow-sm">
        <h3 class="font-serif text-lg font-bold mb-4 pb-3 border-b flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
          Read Next
        </h3>

        <div v-if="related.length === 0" class="text-sm text-muted-foreground">
          No related articles found.
        </div>

        <div v-else class="divide-y divide-border/60">
          <div v-for="rel in related" :key="rel.slug" class="py-3 first:pt-0 last:pb-0">
            <NewsCompactArticleCard :article="rel" variant="minimal" />
          </div>
        </div>
      </div>
    </aside>
  </div>
</template>