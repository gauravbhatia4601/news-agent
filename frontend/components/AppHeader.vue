<script setup lang="ts">
const router = useRouter()
const route = useRoute()
const searchQuery = ref('')
const showSearch = ref(false)
const showMobileMenu = ref(false)
const searchInput = ref<HTMLInputElement>()

const api = useNewsApi()
const { data: categories } = await useAsyncData('nav-categories-tree', () => api.getCategoryTree(), {
  default: () => [] as any[],
})

const goSearch = () => {
  const q = searchQuery.value.trim()
  if (!q) return
  router.push(`/search?q=${encodeURIComponent(q)}`)
  searchQuery.value = ''
  showSearch.value = false
}

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
        <h1 class="font-display text-[2.25rem] font-bold tracking-tight sm:text-[2.75rem] md:text-[3.25rem]">
          THE NEURAL JOURNAL
        </h1>
      </NuxtLink>
    </div>
  </header>

  <!-- Category navigation bar -->
  <nav class="sticky top-0 z-50 w-full border-b border-border bg-background">
    <div class="mx-auto flex max-w-[960px] items-center px-5 xl:max-w-[1280px]">
      <!-- Desktop: inline categories with horizontal scroll -->
      <div class="flex flex-1 items-center gap-px py-1.5 overflow-x-auto scrollbar-none lg:overflow-visible">
        <NuxtLink
          to="/"
          class="shrink-0 px-2.5 py-1 font-label text-xs font-bold uppercase tracking-[0.062em] transition-colors"
          :class="route.path === '/' ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
        >
          Home
        </NuxtLink>

        <template v-for="cat in categories" :key="cat.id">
          <span class="mx-1 h-3 w-px bg-border shrink-0 hidden lg:inline-block" />
          <!-- Desktop: dropdown -->
          <div class="relative shrink-0 dropdown hidden lg:block">
            <NuxtLink
              :to="`/category/${cat.slug}`"
              class="block px-2.5 py-1 font-label text-xs font-bold uppercase tracking-[0.062em] transition-colors"
              :class="route.path === `/category/${cat.slug}` ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
            >
              {{ cat.name }}
            </NuxtLink>
            <div v-if="cat.children?.length" class="dropdown-menu absolute left-0 top-full hidden pt-1 z-50">
              <div class="border border-border bg-background py-2 shadow-lg min-w-[200px]">
                <NuxtLink
                  v-for="child in cat.children"
                  :key="child.id"
                  :to="`/category/${child.slug}`"
                  class="block px-4 py-1.5 font-label text-xs text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                >
                  {{ child.name }}
                </NuxtLink>
              </div>
            </div>
          </div>
          <!-- Mobile: flat links, scrollable -->
          <NuxtLink
            :to="`/category/${cat.slug}`"
            class="shrink-0 px-2.5 py-1 font-label text-xs font-bold uppercase tracking-[0.062em] transition-colors lg:hidden"
            :class="route.path === `/category/${cat.slug}` ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
          >
            {{ cat.name }}
          </NuxtLink>
        </template>

        <span class="mx-1 h-3 w-px bg-border shrink-0" />
        <NuxtLink
          to="/trending"
          class="shrink-0 px-2.5 py-1 font-label text-xs font-bold uppercase tracking-[0.062em] transition-colors"
          :class="route.path === '/trending' ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
        >
          Trending
        </NuxtLink>
      </div>

      <!-- Search icon -->
      <button
        class="ml-2 shrink-0 inline-flex items-center justify-center h-9 w-9 text-muted-foreground hover:text-foreground transition-colors"
        @click="showSearch = !showSearch"
        aria-label="Toggle search"
      >
        <svg v-if="!showSearch" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Search panel -->
    <div v-if="showSearch" class="border-t border-border bg-muted/30 px-5 py-3">
      <div class="mx-auto max-w-[960px] xl:max-w-[1280px]">
        <form @submit.prevent="goSearch" class="relative">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="search"
            placeholder="Search stories..."
            class="h-10 w-full border border-border bg-background pl-10 pr-4 font-serif text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-foreground transition-colors"
          >
        </form>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.dropdown:hover .dropdown-menu,
.dropdown:focus-within .dropdown-menu {
  display: block;
}

.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
</style>