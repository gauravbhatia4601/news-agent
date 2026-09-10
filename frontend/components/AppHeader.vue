<script setup lang="ts">
const router = useRouter()
const route = useRoute()
const searchQuery = ref('')
const showSearch = ref(false)
const showMobileMenu = ref(false)
const searchInput = ref<HTMLInputElement>()
const searchWrap = ref<HTMLElement>()

const api = useNewsApi()
const { data: categories } = await useAsyncData('nav-categories-tree', () => api.getCategoryTree(), {
  default: () => [] as any[],
})

const filteredCategories = computed(() => {
  return (categories.value ?? []).filter((cat: any) => cat.slug !== 'artificial-intelligence')
})

// ── Live search (typeahead) ──
const suggestions = ref<any[]>([])
const isSearching = ref(false)
const activeIndex = ref(-1)
let debounceTimer: ReturnType<typeof setTimeout> | null = null
let searchSeq = 0

const hasDropdown = computed(() =>
  showSearch.value && searchQuery.value.trim().length >= 2,
)

watch(searchQuery, (q) => {
  const query = q.trim()
  if (debounceTimer) clearTimeout(debounceTimer)
  if (query.length < 2) {
    suggestions.value = []
    isSearching.value = false
    return
  }
  isSearching.value = true
  const seq = ++searchSeq
  debounceTimer = setTimeout(async () => {
    try {
      const results = await api.search(query, { perPage: 6 })
      // Last-write-wins: ignore stale responses after rapid typing.
      if (seq === searchSeq) suggestions.value = results
    } catch {
      if (seq === searchSeq) suggestions.value = []
    } finally {
      if (seq === searchSeq) isSearching.value = false
    }
  }, 300)
})

const activeSuggestion = computed(() => suggestions.value[activeIndex.value] ?? null)

const selectSuggestion = (article: any) => {
  router.push(`/article/${article.slug}`)
  resetSearch()
}

const goSearch = () => {
  const q = searchQuery.value.trim()
  if (!q) return
  router.push(`/search?q=${encodeURIComponent(q)}`)
  resetSearch()
}

const resetSearch = () => {
  searchQuery.value = ''
  suggestions.value = []
  activeIndex.value = -1
  showSearch.value = false
}

const onSearchKeydown = (e: KeyboardEvent) => {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    activeIndex.value = Math.min(activeIndex.value + 1, suggestions.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    activeIndex.value = Math.max(activeIndex.value - 1, -1)
  } else if (e.key === 'Enter' && activeIndex.value >= 0 && activeSuggestion.value) {
    e.preventDefault()
    selectSuggestion(activeSuggestion.value)
  } else if (e.key === 'Escape') {
    resetSearch()
    searchInput.value?.blur()
  }
}

// ── Hoisted category dropdown ──
// The panel renders OUTSIDE the horizontally-scrollable container so scrolling
// can never clip it. Position tracks the hovered trigger link.
const navRow = ref<HTMLElement>()
const openDropdown = ref<any>(null)
const dropdownLeft = ref(0)

const onCategoryEnter = (cat: any, e: MouseEvent) => {
  const nav = navRow.value
  if (!nav) return
  const rect = (e.currentTarget as HTMLElement).getBoundingClientRect()
  dropdownLeft.value = rect.left - nav.getBoundingClientRect().left
  openDropdown.value = cat
}

// Close the dropdown when clicking outside the search area.
const onDocumentClick = (e: MouseEvent) => {
  if (showSearch.value && searchWrap.value && !searchWrap.value.contains(e.target as Node)) {
    suggestions.value = []
    activeIndex.value = -1
  }
}
onMounted(() => document.addEventListener('click', onDocumentClick))
onUnmounted(() => document.removeEventListener('click', onDocumentClick))

// Reset state on navigation (dropdown + panel).
watch(() => route.fullPath, () => {
  if (searchQuery.value || suggestions.value.length) resetSearch()
  openDropdown.value = null
  showMobileMenu.value = false
})

function todayDate() {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
  })
}

watch(showSearch, (val) => {
  if (val) {
    nextTick(() => searchInput.value?.focus())
  }
})

watch(() => route.path, () => {
  showMobileMenu.value = false
})
</script>

<template>
  <!-- Nav-hat: dark bar -->
  <div class="w-full bg-primary text-primary-foreground text-xs">
    <div class="mx-auto flex h-8 max-w-[960px] items-center justify-between px-5 xl:max-w-[1280px]">
      <div class="flex items-center gap-3 sm:gap-5 min-w-0">
        <span class="font-label font-bold uppercase tracking-[0.062em] truncate">The Neural Journal</span>
        <span class="hidden sm:inline h-3 w-px bg-white/20 shrink-0" />
        <span class="hidden sm:inline font-label uppercase tracking-[0.062em] text-white/80 shrink-0">Edition: India</span>
      </div>
      <div class="flex items-center gap-3">
        <span class="hidden lg:block font-label text-white/60">{{ todayDate() }}</span>
      </div>
    </div>
  </div>

  <!-- Market ticker -->
  <MarketTicker />

  <!-- Main masthead -->
  <header class="w-full border-b border-border bg-background">
    <div class="mx-auto flex max-w-[960px] flex-col items-center px-5 py-2 xl:max-w-[1280px]">
      <NuxtLink to="/" class="block">
        <span class="font-display text-[2.25rem] font-bold tracking-tight sm:text-[2.75rem] md:text-[3.25rem]">
          THE NEURAL JOURNAL
        </span>
      </NuxtLink>
    </div>
  </header>

  <!-- Category navigation bar -->
  <nav class="sticky top-0 z-50 w-full border-b border-border bg-background">
    <div
      ref="navRow"
      class="relative mx-auto flex max-w-[960px] items-center px-5 xl:max-w-[1280px]"
      @mouseleave="openDropdown = null"
    >
      <!-- Category links: scrollable when tight; dropdowns are hoisted to nav
           level so the scroll container can never clip them -->
      <div class="flex min-w-0 flex-1 items-center gap-px py-1.5 overflow-x-auto scrollbar-none lg:justify-between">
        <NuxtLink
          to="/"
          class="shrink-0 px-2 py-1 font-label text-xs font-bold uppercase tracking-[0.062em] transition-colors"
          :class="route.path === '/' ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
        >
          Home
        </NuxtLink>

        <NuxtLink
          to="/category/artificial-intelligence"
          class="shrink-0 px-2 py-1 font-label text-xs font-bold uppercase tracking-[0.062em] transition-colors"
          :class="route.path === '/category/artificial-intelligence' ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
        >
          AI
        </NuxtLink>

        <template v-for="cat in filteredCategories" :key="cat.id">
          <!-- Desktop trigger: hover opens the hoisted panel -->
          <div
            class="relative shrink-0 hidden lg:block"
            @mouseenter="onCategoryEnter(cat, $event)"
          >
            <NuxtLink
              :to="`/category/${cat.slug}`"
              class="block px-2 py-1 font-label text-xs font-bold uppercase tracking-[0.062em] transition-colors"
              :class="route.path === `/category/${cat.slug}` ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
            >
              {{ cat.name }}
            </NuxtLink>
          </div>
          <!-- Mobile: flat links, scrollable -->
          <NuxtLink
            :to="`/category/${cat.slug}`"
            class="shrink-0 px-2 py-1 font-label text-xs font-bold uppercase tracking-[0.062em] transition-colors lg:hidden"
            :class="route.path === `/category/${cat.slug}` ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
          >
            {{ cat.name }}
          </NuxtLink>
        </template>
      </div>

      <!-- Hoisted dropdown panel — outside the scroll container, never clipped -->
      <div
        v-if="openDropdown?.children?.length"
        class="absolute top-full z-50 pt-1"
        :style="{ left: dropdownLeft + 'px' }"
      >
        <div class="max-h-[420px] min-w-[220px] overflow-y-auto border border-border bg-background py-2 shadow-lg">
          <NuxtLink
            v-for="child in openDropdown.children"
            :key="child.id"
            :to="`/category/${child.slug}`"
            class="block px-4 py-1.5 font-label text-xs text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
          >
            {{ child.name }}
          </NuxtLink>
        </div>
      </div>

      <!-- Fixed right cluster: Trending + search toggle -->
      <div class="ml-1 flex shrink-0 items-center bg-background">
        <NuxtLink
          to="/trending"
          class="px-2 py-1 font-label text-xs font-bold uppercase tracking-[0.062em] transition-colors"
          :class="route.path === '/trending' ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
        >
          Trending
        </NuxtLink>

        <!-- Search icon -->
        <button
          class="ml-1 inline-flex items-center justify-center h-9 w-9 text-muted-foreground hover:text-foreground transition-colors focus-ring"
          @click="showSearch = !showSearch"
          aria-label="Toggle search"
          :aria-expanded="showSearch"
        >
          <svg v-if="!showSearch" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    </div>

    <!-- Search panel with live suggestions -->
    <div v-if="showSearch" class="border-t border-border bg-muted/30 px-5 py-3">
      <div class="mx-auto max-w-[960px] xl:max-w-[1280px]" ref="searchWrap">
        <form @submit.prevent="goSearch" class="relative" role="search">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="search"
            placeholder="Search stories..."
            autocomplete="off"
            role="combobox"
            aria-expanded="true"
            aria-controls="search-suggestions"
            aria-autocomplete="list"
            class="h-10 w-full border border-border bg-background pl-10 pr-4 font-serif text-sm text-foreground placeholder:text-muted-foreground focus-ring transition-colors"
            @keydown="onSearchKeydown"
          >

          <!-- Suggestions dropdown -->
          <div
            v-if="searchQuery.trim().length >= 2 && (isSearching || suggestions.length > 0)"
            class="absolute left-0 right-0 top-full z-50 mt-1 border border-border bg-background shadow-lg max-h-[60vh] overflow-y-auto"
          >
            <div v-if="isSearching && suggestions.length === 0" class="px-4 py-3 font-serif text-sm text-muted-foreground">
              Searching…
            </div>
            <template v-else>
              <button
                v-for="(article, i) in suggestions"
                :key="article.slug"
                type="button"
                class="block w-full px-4 py-2 text-left transition-colors"
                :class="i === activeIndex ? 'bg-muted' : 'hover:bg-muted'"
                @click="selectSuggestion(article)"
                @mouseenter="activeIndex = i"
              >
                <span class="block font-label text-[10px] uppercase tracking-[0.062em] text-muted-foreground">
                  {{ article.category?.name || 'News' }} · {{ timeAgo(article.published_at) }}
                </span>
                <span class="block font-serif text-sm text-foreground line-clamp-1">{{ article.title }}</span>
              </button>
            </template>
          </div>

          <!-- No-results hint (below threshold keeps the form clean) -->
          <div
            v-else-if="searchQuery.trim().length >= 2 && !isSearching"
            class="absolute left-0 right-0 top-full z-50 mt-1 border border-border bg-background shadow-lg px-4 py-3 font-serif text-sm text-muted-foreground"
          >
            No results for “{{ searchQuery.trim() }}” — press Enter to search everything.
          </div>
        </form>
      </div>
    </div>
  </nav>
</template>