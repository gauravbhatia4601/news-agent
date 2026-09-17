<script setup lang="ts">
import type { CategoryNode, NewsArticleCard } from '~/types/news'

const route = useRoute()
const categorySlug = route.params.name as string
const { public: { siteUrl } } = useRuntimeConfig()

const api = useNewsApi()

const { data: categoryTree } = await useAsyncData(
  `category-tree-${categorySlug}`,
  () => api.getCategoryTree(),
  { default: () => [] as CategoryNode[] }
)

const categoryName = computed(() => {
  const flat = (categoryTree.value ?? []).flatMap((c: CategoryNode) => [c, ...(c.children ?? [])])
  const match = flat.find((c) => c.slug === categorySlug)
  return match?.name ?? categorySlug
})

// URL-based pagination: ?page=N drives SSR-rendered article links.
const currentPage = computed(() => {
  const p = Number(route.query.page ?? 1)
  return Number.isFinite(p) && p >= 1 ? p : 1
})

const perPage = 9

const { data: pageData, pending } = await useAsyncData(
  `category-${categorySlug}-page-${currentPage.value}`,
  () => api.getLatestPaginated({ category: categorySlug, perPage, page: currentPage.value }),
  { watch: [currentPage] },
)

const articles = computed<NewsArticleCard[]>(() => pageData.value?.data ?? [])
const lastPage = computed(() => pageData.value?.meta.last_page ?? 1)
const total = computed(() => pageData.value?.meta.total ?? 0)

const heroRel = useRelativeTime(() => articles.value[0]?.published_at)

// Build category description deterministically (100-155 chars).
// Pagination pages get a page indicator so no two archive pages share a
// description (OpenSEO round-4 duplicate-meta flags on ?page=N).
const categoryDescription = computed(() => {
  const name = categoryName.value
  const page = currentPage.value > 1 ? ` — page ${currentPage.value} of the ${name} archive.` : ''
  const base = `Read the latest ${name} news, in-depth analysis, and breaking updates from The Neural Journal. `
  const tail = 'Stay informed with curated, fact-driven coverage of developing stories and trending topics.'
  const full = (base + tail).slice(0, 155 - page.length).replace(/\s+\S*$/, '') + page
  return full
})

// Canonical: page 1 = self-canonical on path; page 2+ = self-canonical with ?page=N.
const canonicalUrl = computed(() => {
  const base = `${siteUrl}${route.path}`
  return currentPage.value > 1 ? `${base}?page=${currentPage.value}` : base
})

const prevHref = computed(() => {
  if (currentPage.value <= 1) return null
  return currentPage.value === 2 ? `${siteUrl}${route.path}` : `${siteUrl}${route.path}?page=${currentPage.value - 1}`
})

const nextHref = computed(() => {
  if (currentPage.value >= lastPage.value) return null
  return `${siteUrl}${route.path}?page=${currentPage.value + 1}`
})

useHead({
  title: computed(() => currentPage.value > 1 ? `${categoryName.value} News — Page ${currentPage.value}` : `${categoryName.value} News`),
  meta: [
    { name: 'description', content: categoryDescription },
  ],
  link: computed(() => [
    { rel: 'canonical', href: canonicalUrl.value },
    ...(prevHref.value ? [{ rel: 'prev', href: prevHref.value }] : []),
    ...(nextHref.value ? [{ rel: 'next', href: nextHref.value }] : []),
  ]),
})

// Navigation helpers for pagination links.
const page1Href = computed(() => `${route.path}`)
function pageHref(n: number): string {
  return n <= 1 ? page1Href.value : `${route.path}?page=${n}`
}

const pageNumbers = computed(() => {
  const last = lastPage.value
  const cur = currentPage.value
  const pages: (number | '…')[] = []
  const window = 2
  for (let i = 1; i <= last; i++) {
    if (i === 1 || i === last || (i >= cur - window && i <= cur + window)) {
      pages.push(i)
    } else if (pages[pages.length - 1] !== '…') {
      pages.push('…')
    }
  }
  return pages
})
</script>

<template>
  <div class="space-y-8">
    <!-- Breadcrumb + header -->
    <header class="border-b pb-6">
      <div class="flex items-center gap-2 font-label text-xs text-muted-foreground mb-3">
        <NuxtLink to="/" class="hover:text-foreground transition-colors">Home</NuxtLink>
        <span>/</span>
        <span class="text-foreground font-medium capitalize">{{ categoryName }}</span>
      </div>
      <h1 class="font-display text-3xl sm:text-4xl font-bold tracking-tight capitalize mb-2">
        {{ categoryName }}
      </h1>
      <p class="font-serif text-muted-foreground">
        Latest stories in {{ categoryName }}, reported by The Neural Journal's automated newsroom. Each article is synthesized from multiple verified sources and published within minutes of the story breaking. This archive holds every {{ categoryName }} piece we have published, with older coverage one page down.
      </p>
    </header>
    <!-- h2 bridges the h1 to the card h3 headings (heading-order-skip) -->
    <h2 class="sr-only">{{ categoryName }} archive</h2>

    <div v-if="articles.length === 0" class="py-16 text-center font-serif text-muted-foreground">
      No articles found for this category yet.
    </div>

    <template v-else>
      <!-- Hero lead (first article on page 1) -->
      <section v-if="articles[0] && currentPage === 1" class="group">
        <NuxtLink :to="`/article/${articles[0].slug}`" class="block">
          <div v-if="articles[0].image_url" class="mb-4 overflow-hidden">
            <img
              :src="articles[0].image_url"
              :alt="articles[0].title"
              class="h-64 w-full object-cover sm:h-80 transition-transform duration-500 group-hover:scale-[1.02]"
              loading="eager"
            >
          </div>

          <div class="flex items-center gap-2 mb-2">
            <span class="font-label text-[11px] font-bold uppercase tracking-[0.062em] text-muted-foreground">{{ articles[0].category?.name ?? '' }}</span>
            <span class="text-border">|</span>
            <span class="font-label text-[11px] text-muted-foreground">{{ heroRel }}</span>
            <span v-if="isNew(articles[0].published_at)" class="font-label text-[10px] font-bold uppercase tracking-[0.062em] text-secondary">New</span>
          </div>

          <h2 class="font-display text-2xl sm:text-3xl md:text-[2.25rem] font-bold leading-[1.15] mb-3 group-hover:underline decoration-1 underline-offset-4">
            {{ articles[0].title }}
          </h2>

          <p v-if="articles[0].excerpt" class="font-serif text-sm sm:text-base leading-relaxed text-muted-foreground mb-4 max-w-3xl">
            {{ articles[0].excerpt }}
          </p>

          <div class="flex items-center gap-2 font-label text-xs text-muted-foreground">
            <span class="font-medium text-foreground">{{ articles[0].author }}</span>
            <span>·</span>
            <span>{{ articles[0].read_time_minutes }} min read</span>
          </div>
        </NuxtLink>
      </section>

      <!-- Grid for the rest (all articles on pages 2+) -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-8">
        <NewsCompactArticleCard
          v-for="article in (currentPage === 1 ? articles.slice(1) : articles)"
          :key="article.slug"
          :article="article"
          variant="default"
          :show-image="!!article.image_url || !!article.thumbnail_url"
        />
      </div>

      <!-- URL-based pagination: crawlers see all page links as <a> tags -->
      <nav v-if="lastPage > 1" class="flex items-center justify-center gap-2 pt-6" aria-label="Pagination">
        <NuxtLink
          v-if="currentPage > 1"
          :to="pageHref(currentPage - 1)"
          class="border border-border px-4 py-2 font-label text-xs font-bold uppercase tracking-[0.062em] text-muted-foreground hover:text-foreground hover:border-foreground transition-colors"
        >
          ← Prev
        </NuxtLink>

        <template v-for="(p, i) in pageNumbers" :key="i">
          <span v-if="p === '…'" class="font-label text-xs text-muted-foreground px-1">…</span>
          <NuxtLink
            v-else
            :to="pageHref(p)"
            class="border px-3 py-2 font-label text-xs font-bold transition-colors"
            :class="p === currentPage ? 'border-foreground text-foreground' : 'border-border text-muted-foreground hover:text-foreground hover:border-foreground'"
          >
            {{ p }}
          </NuxtLink>
        </template>

        <NuxtLink
          v-if="currentPage < lastPage"
          :to="pageHref(currentPage + 1)"
          class="border border-border px-4 py-2 font-label text-xs font-bold uppercase tracking-[0.062em] text-muted-foreground hover:text-foreground hover:border-foreground transition-colors"
        >
          Next →
        </NuxtLink>
      </nav>
    </template>
  </div>
</template>