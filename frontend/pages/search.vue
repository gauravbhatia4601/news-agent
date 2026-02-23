<script setup lang="ts">
const route = useRoute()
const api = useNewsApi()

const query = computed(() => String(route.query.q ?? '').trim())
const results = ref(await (query.value ? api.search(query.value, { perPage: 24 }) : Promise.resolve([])))

watch(
  () => route.query.q,
  async () => {
    results.value = query.value ? await api.search(query.value, { perPage: 24 }) : []
  }
)

useHead({
  title: () => query.value ? `Search: ${query.value} — The Trust Journal` : 'Search — The Trust Journal',
})
</script>

<template>
  <div class="space-y-8">
    <header class="border-b pb-6">
      <h1 class="font-serif text-3xl sm:text-4xl font-bold tracking-tight mb-2">Search</h1>
      <p class="text-muted-foreground">
        <template v-if="query">Results for: <span class="font-medium text-foreground">"{{ query }}"</span></template>
        <template v-else>Enter a keyword from the search bar above.</template>
      </p>
    </header>

    <div v-if="!query" class="py-16 text-center text-muted-foreground">
      Enter a keyword to find stories.
    </div>

    <div v-else-if="results.length === 0" class="py-16 text-center text-muted-foreground">
      No results found for "{{ query }}".
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-8">
      <NewsCompactArticleCard
        v-for="item in results"
        :key="item.slug"
        :article="item"
        variant="default"
        :show-image="!!item.image_url || !!item.thumbnail_url"
      />
    </div>
  </div>
</template>
