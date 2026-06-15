<template>
  <div class="relative flex min-h-screen flex-col bg-background">
    <AppHeader />
    <main class="flex-1">
      <div class="mx-auto max-w-[960px] px-5 py-6 md:py-8 xl:max-w-[1280px]">
        <slot />
      </div>
    </main>
    <AppFooter />
  </div>
</template>
