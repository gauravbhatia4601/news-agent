<script setup lang="ts">
const api = useNewsApi()
const route = useRoute()

const { data: categories } = await useAsyncData('nav-categories', () => api.getCategories(), {
  default: () => [] as string[],
})

const isActive = (cat: string) => {
  return route.path === `/category/${cat}`
}
</script>

<template>
  <nav class="sticky top-16 z-40 w-full border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
    <div class="container max-w-screen-2xl">
      <div class="flex items-center gap-1 overflow-x-auto scrollbar-none py-2 -mx-1 px-1">
        <NuxtLink
          to="/"
          class="shrink-0 rounded-md px-3 py-1.5 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
          :class="route.path === '/' ? 'bg-accent text-accent-foreground' : 'text-muted-foreground'"
        >
          Home
        </NuxtLink>

        <span class="mx-1 h-4 w-px bg-border shrink-0" />

        <NuxtLink
          v-for="cat in categories"
          :key="cat"
          :to="`/category/${cat}`"
          class="shrink-0 rounded-md px-3 py-1.5 text-sm font-medium capitalize transition-colors hover:bg-accent hover:text-accent-foreground"
          :class="isActive(cat) ? 'bg-accent text-accent-foreground' : 'text-muted-foreground'"
        >
          {{ cat }}
        </NuxtLink>

        <span class="mx-1 h-4 w-px bg-border shrink-0" />

        <NuxtLink
          to="/trending"
          class="shrink-0 rounded-md px-3 py-1.5 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
          :class="route.path === '/trending' ? 'bg-accent text-accent-foreground' : 'text-muted-foreground'"
        >
          Trending
        </NuxtLink>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
