<template>
  <div class="relative flex min-h-screen flex-col bg-background">
    <AppHeader />
    <NewsTopCategoryNav />
    <main class="flex-1">
      <div class="container max-w-screen-2xl py-6 md:py-8 lg:py-10">
        <slot />
      </div>
    </main>
    <AppFooter />
  </div>
</template>
