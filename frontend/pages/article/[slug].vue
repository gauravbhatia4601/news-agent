<script setup lang="ts">
const route = useRoute()
const articleSlug = route.params.slug as string

const api = useNewsApi()
const { data: article } = await useAsyncData(`article-${articleSlug}`, async () => {
  const fetched = await api.getArticle(articleSlug)
  if (!fetched) {
    // Keep the styled not-found template but still serve a real 404 to crawlers.
    if (import.meta.server) {
      const event = useRequestEvent()
      if (event) setResponseStatus(event, 404)
    }
    throw createError({ statusCode: 404, statusMessage: 'Article not found' })
  }
  return fetched
})
const { data: related } = await useAsyncData(`related-${articleSlug}`, () => api.getRelated(articleSlug), { default: () => [] as any[] })

const siteUrl = useRuntimeConfig().public.siteUrl

const sanitizedContent = computed(() => {
  if (!article.value?.content) return ''
  return sanitizeHtml(article.value.content)
})

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
  })
}

function generateImageAlt(): string {
  if (!article.value) return ''
  const keywords = article.value.meta_keywords || article.value.entities?.primary_topic_term || article.value.title || ''
  return `${article.value.category?.name || 'News'} - ${keywords.split(',')[0] || article.value.title}: Latest news and analysis`
}

useSeoMeta({
  title: () => article.value ? (article.value.meta_title || article.value.title) : 'Article Not Found',
  description: () => article.value ? (article.value.meta_description || article.value.title) : '',
  keywords: () => article.value?.meta_keywords || '',
  ogTitle: () => article.value ? (article.value.meta_title || article.value.title) : '',
  ogDescription: () => article.value ? (article.value.meta_description || article.value.title) : '',
  ogImage: () => article.value?.image_url || undefined,
  ogUrl: () => article.value ? `${siteUrl}/article/${article.value.slug}` : '',
  twitterTitle: () => article.value ? (article.value.meta_title || article.value.title) : '',
  twitterDescription: () => article.value ? (article.value.meta_description || article.value.title) : '',
  twitterImage: () => article.value?.image_url || undefined,
})

useHead({
  link: [
    { rel: 'canonical', href: article.value ? `${siteUrl}/article/${article.value.slug}` : '' },
  ],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: () => JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'NewsArticle',
        headline: article.value?.meta_title || article.value?.title,
        description: article.value?.meta_description || '',
        keywords: article.value?.meta_keywords || '',
        datePublished: article.value?.published_at,
        dateModified: article.value?.published_at,
        author: {
          '@type': 'Person',
          name: article.value?.author || 'AI News Desk',
        },
        publisher: {
          '@type': 'Organization',
          name: 'The Neural Journal',
          url: siteUrl,
          logo: {
            '@type': 'ImageObject',
            url: `${siteUrl}/logo.png`,
          },
        },
        mainEntityOfPage: {
          '@type': 'WebPage',
          '@id': `${siteUrl}/article/${article.value?.slug}`,
        },
        image: article.value?.image_url || undefined,
        wordCount: article.value?.content ? article.value.content.replace(/<[^>]*>/g, '').split(/\s+/).length : undefined,
        about: article.value?.entities ? {
          '@type': 'Thing',
          name: article.value.entities.primary_topic_term,
        } : undefined,
      }),
    },
    {
      type: 'application/ld+json',
      innerHTML: () => JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
          {
            '@type': 'ListItem',
            position: 1,
            name: 'Home',
            item: siteUrl,
          },
          article.value?.category ? {
            '@type': 'ListItem',
            position: 2,
            name: article.value.category.name,
            item: `${siteUrl}/category/${article.value.category.slug}`,
          } : null,
          {
            '@type': 'ListItem',
            position: 3,
            name: article.value?.title || '',
            item: `${siteUrl}/article/${article.value?.slug}`,
          },
        ].filter(Boolean),
      }),
    },
    ...(article.value?.faq_section?.length ? [{
      type: 'application/ld+json',
      innerHTML: () => JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: article.value!.faq_section.map((faq: { question: string; answer: string }) => ({
          '@type': 'Question',
          name: faq.question,
          acceptedAnswer: {
            '@type': 'Answer',
            text: faq.answer,
          },
        })),
      }),
    }] : []),
  ],
})
</script>

<template>
  <div v-if="!article" class="py-20 text-center">
    <h1 class="font-display text-3xl font-bold mb-4">Article Not Found</h1>
    <p class="font-serif text-muted-foreground mb-8">We couldn't find the article you were looking for.</p>
    <NuxtLink to="/" class="inline-flex h-10 items-center justify-center bg-foreground px-8 font-label text-xs font-bold uppercase tracking-[0.062em] text-background hover:opacity-90 transition-opacity">Return Home</NuxtLink>
  </div>

  <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-12">
    <!-- Main Article Content -->
    <article class="lg:col-span-8 space-y-8">
      <header class="space-y-6 border-b pb-8">
        <nav aria-label="Breadcrumb" class="font-label text-xs text-muted-foreground">
          <ol class="flex items-center gap-x-1.5 flex-wrap">
            <li><NuxtLink to="/" class="hover:text-foreground transition-colors">Home</NuxtLink></li>
            <li aria-hidden="true">/</li>
            <li v-if="article.category">
              <NuxtLink :to="`/category/${article.category.slug}`" class="font-bold uppercase tracking-[0.062em] hover:text-foreground transition-colors">{{ article.category.name }}</NuxtLink>
            </li>
          </ol>
        </nav>

        <h1 class="font-display text-3xl sm:text-4xl md:text-5xl font-bold leading-tight tracking-tight">{{ article.title }}</h1>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 font-label text-xs text-muted-foreground">
          <div class="flex items-center gap-2">
            <span class="font-medium text-foreground">By {{ article.author }}</span>
            <span>·</span>
            <span>{{ formatDate(article.published_at) }}</span>
          </div>
          <div class="flex items-center gap-4">
            <span class="flex items-center gap-1">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              {{ article.read_time_minutes }} min read
            </span>
            <span class="flex items-center gap-1">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              {{ article.views }} views
            </span>
          </div>
        </div>

        <div v-if="article.image_url" class="overflow-hidden">
          <img
            :src="article.image_url"
            :alt="generateImageAlt()"
            width="1200"
            height="675"
            class="h-64 w-full object-cover md:h-80"
            loading="eager"
            fetchpriority="high"
          >
        </div>
      </header>

      <AdSlot variant="horizontal" label="Advertisement" />

      <div class="article-body">
        <div v-html="sanitizedContent" />
      </div>

      <!-- FAQ Section -->
      <section v-if="article.faq_section?.length" class="mt-12 pt-8 border-t">
        <h2 class="font-display text-2xl font-bold mb-6">Frequently Asked Questions</h2>
        <div class="space-y-4">
          <details v-for="(faq, idx) in article.faq_section" :key="idx" class="border rounded">
            <summary class="font-display font-bold p-4 cursor-pointer hover:bg-accent transition-colors select-none">
              {{ faq.question }}
            </summary>
            <div class="px-4 pb-4 font-serif text-sm leading-relaxed">
              {{ faq.answer }}
            </div>
          </details>
        </div>
      </section>

      <!-- Entities Tags -->
      <section v-if="article.entities" class="mt-8 pt-6 border-t">
        <div class="flex flex-wrap gap-2">
          <span
            v-for="person in article.entities.people"
            :key="person"
            class="inline-flex items-center rounded border px-2.5 py-0.5 font-label text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
          >
            {{ person }}
          </span>
          <span
            v-for="org in article.entities.organizations"
            :key="org"
            class="inline-flex items-center rounded border px-2.5 py-0.5 font-label text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
          >
            {{ org }}
          </span>
          <span
            v-for="loc in article.entities.locations"
            :key="loc"
            class="inline-flex items-center rounded border px-2.5 py-0.5 font-label text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
          >
            {{ loc }}
          </span>
        </div>
      </section>

      <footer v-if="article.sources?.length" class="mt-12 pt-8 border-t">
        <h3 class="font-display text-xl font-bold mb-4">Sources & References</h3>
        <ul class="space-y-3">
          <li v-for="source in article.sources" :key="source.url" class="font-serif text-sm border p-4">
            <a :href="source.url" target="_blank" rel="noopener noreferrer" class="font-medium hover:underline text-foreground group flex items-start gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0 opacity-50 group-hover:opacity-100"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
              <span>{{ source.name }}</span>
            </a>
          </li>
        </ul>
      </footer>
    </article>

    <!-- Sidebar / Read Next -->
    <aside class="lg:col-span-4">
      <div class="lg:sticky lg:top-[7.5rem]">
        <h3 class="font-display text-xl font-bold mb-4 pb-2 border-b-strong">
          Read Next
        </h3>

        <div v-if="related && related.length === 0" class="font-label text-xs text-muted-foreground">
          No related articles found.
        </div>

        <div v-else class="divide-y divide-border">
          <div v-for="rel in related" :key="rel.slug" class="py-3 first:pt-0 last:pb-0">
            <NewsCompactArticleCard :article="rel" variant="minimal" />
          </div>
        </div>

        <AdSlot variant="vertical" label="Advertisement" />
      </div>
    </aside>
  </div>
</template>

<style scoped>
.article-body :deep(h2) {
  font-family: 'Playfair Display', serif;
  font-weight: 700;
  margin-top: 2rem;
  margin-bottom: 1rem;
  font-size: 1.5rem;
  line-height: 1.3;
}

.article-body :deep(h3) {
  font-family: 'Playfair Display', serif;
  font-weight: 700;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  font-size: 1.25rem;
}

.article-body :deep(p) {
  font-family: 'Source Serif 4', serif;
  margin-bottom: 1.25rem;
  line-height: 1.75;
  font-size: 1.0625rem;
}

.article-body :deep(a) {
  color: hsl(var(--foreground));
  text-decoration: underline;
  text-underline-offset: 2px;
}

.article-body :deep(a:hover) {
  opacity: 0.7;
}

.article-body :deep(ul),
.article-body :deep(ol) {
  margin-bottom: 1.25rem;
  padding-left: 1.5rem;
}

.article-body :deep(li) {
  margin-bottom: 0.5rem;
}

.article-body :deep(blockquote) {
  border-left: 2px solid hsl(var(--border));
  padding-left: 1rem;
  margin: 1.5rem 0;
  font-style: italic;
  color: hsl(var(--muted-foreground));
}
</style>
