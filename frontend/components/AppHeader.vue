<script setup lang="ts">
const router = useRouter()
const searchQuery = ref('')
const showMobileSearch = ref(false)

const goSearch = () => {
  const q = searchQuery.value.trim()
  if (!q) return
  router.push(`/search?q=${encodeURIComponent(q)}`)
  showMobileSearch.value = false
}

function todayDate() {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
  })
}
</script>

<template>
  <header class="sticky top-0 z-50 w-full border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
    <div class="container flex h-14 max-w-screen-2xl items-center justify-between">
      <!-- Brand -->
      <NuxtLink to="/" class="flex items-center gap-2">
        <span class="font-serif text-xl font-bold tracking-tight">The Trust Journal</span>
      </NuxtLink>

      <!-- Center: Today's date (hidden on small screens) -->
      <span class="hidden lg:block text-xs text-muted-foreground tracking-wide">
        {{ todayDate() }}
      </span>

      <!-- Right: Search -->
      <div class="flex items-center gap-2">
        <!-- Desktop search -->
        <form @submit.prevent="goSearch" class="relative hidden md:block">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Search stories..."
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring pl-9 md:w-[240px] lg:w-[320px]"
          >
        </form>

        <!-- Mobile search toggle -->
        <button
          class="md:hidden inline-flex items-center justify-center rounded-md h-9 w-9 text-muted-foreground hover:text-foreground transition-colors"
          @click="showMobileSearch = !showMobileSearch"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
        </button>
      </div>
    </div>

    <!-- Mobile search bar -->
    <div v-if="showMobileSearch" class="md:hidden border-t border-border/40 px-4 py-2">
      <form @submit.prevent="goSearch" class="relative">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Search stories..."
          class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring pl-9"
          autofocus
        >
      </form>
    </div>
  </header>
</template>
