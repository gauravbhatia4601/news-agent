<script setup lang="ts">
const api = useNewsApi()
const { data: categories } = await useAsyncData('browse-categories', () => api.getCategoryTree(), { default: () => [] as any[] })

useHead({
  title: 'Browse Topics',
  meta: [
    { name: 'description', content: 'Browse every newsroom section on The Neural Journal — politics, business, technology, sports, world affairs and more.' },
  ],
  link: [
    { rel: 'canonical', href: useCanonical() },
  ],
})
usePageSeo('Browse Topics', 'Browse every newsroom section on The Neural Journal — politics, business, technology, sports, world affairs and more.')
</script>

<template>
  <div class="space-y-10">
    <header class="border-b pb-8">
      <h1 class="font-display text-4xl font-bold tracking-tight mb-4">Browse Topics</h1>
      <p class="font-serif text-xl text-muted-foreground mb-4">Explore all our news categories and stay informed on the topics that matter most to you.</p>
      <p class="font-serif text-muted-foreground">The Neural Journal organizes every story into editorial sections — from national politics and state coverage to business, technology, defence, sports and world affairs. Each section page lists the latest articles first and keeps a complete archive one page down, so you can follow a beat for as long as the story runs. Every piece is synthesized from multiple verified sources by our automated newsroom and quality-checked before it goes live.</p>
    </header>

    <div v-for="parent in categories" :key="parent.id" class="space-y-3">
      <h2 class="font-display text-xl font-bold">{{ parent.name }}</h2>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <NuxtLink
          v-for="child in parent.children"
          :key="child.id"
          :to="`/category/${child.slug}`"
          class="group flex h-32 flex-col items-center justify-center border p-6 transition-colors hover:bg-muted/50"
        >
          <span class="font-display text-lg font-bold group-hover:underline underline-offset-4 decoration-1">{{ child.name }}</span>
        </NuxtLink>
      </div>
    </div>
  </div>
</template>
