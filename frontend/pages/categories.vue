<script setup lang="ts">
const api = useNewsApi()
const categories = await api.getCategories()
</script>

<template>
  <div class="space-y-10">
    <header class="border-b pb-8">
      <h1 class="font-serif text-4xl font-bold tracking-tight mb-4">Browse Topics</h1>
      <p class="text-xl text-muted-foreground">Explore all our news categories and stay informed on the topics that matter most to you.</p>
    </header>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <NuxtLink 
        v-for="category in categories" 
        :key="category" 
        :to="`/category/${category}`"
        class="group flex h-32 flex-col items-center justify-center rounded-xl border bg-card p-6 shadow-sm transition-all hover:bg-muted/50 hover:shadow"
      >
        <span class="font-serif text-lg font-bold capitalize text-primary group-hover:underline underline-offset-4 decoration-primary/50">{{ category }}</span>
      </NuxtLink>
    </div>
  </div>
</template>