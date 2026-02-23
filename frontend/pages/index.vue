<script setup lang="ts">
const api = useNewsApi()

const [featured, headlines, latest, categories] = await Promise.all([
  api.getFeatured(),
  api.getHeadlines(6),
  api.getLatest({ perPage: 6 }),
  api.getCategories(),
])

const categorySections = await Promise.all(
  categories.map(async (category) => ({
    name: category,
    articles: await api.getLatest({ category, perPage: 5 }),
  }))
)

useHead({
  title: 'The Trust Journal — Latest News',
  meta: [
    { name: 'description', content: 'Stay informed with the latest news across technology, politics, sports, entertainment and more.' },
  ],
})
</script>

<template>
  <div class="space-y-10">
    <!-- Top section: Hero + Headlines Rail -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
      <!-- Hero + Latest briefs -->
      <div class="lg:col-span-8 space-y-10">
        <!-- Hero story -->
        <NewsHeroLead v-if="featured" :article="featured" />

        <!-- Thin divider -->
        <div v-if="featured && latest.length > 0" class="h-px bg-border" />

        <!-- Latest updates: brief list -->
        <section v-if="latest.length > 0">
          <h2 class="font-serif text-xl font-bold mb-4 flex items-center gap-4">
            Latest
            <span class="h-px flex-1 bg-border" />
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 divide-y sm:divide-y-0">
            <div v-for="article in latest" :key="article.slug" class="py-3 first:pt-0 sm:py-0">
              <NewsCompactArticleCard :article="article" variant="default" :show-image="!!article.image_url || !!article.thumbnail_url" />
            </div>
          </div>
        </section>
      </div>

      <!-- Headlines rail -->
      <div class="lg:col-span-4">
        <div class="lg:sticky lg:top-[7.5rem]">
          <NewsHeadlinesRail v-if="headlines.length > 0" :headlines="headlines" />
        </div>
      </div>
    </div>

    <!-- Category sections -->
    <template v-if="categorySections.length > 0">
      <div class="h-px bg-border" />
      <div class="space-y-10">
        <NewsCategorySection
          v-for="section in categorySections"
          :key="section.name"
          :name="section.name"
          :articles="section.articles"
        />
      </div>
    </template>
  </div>
</template>
