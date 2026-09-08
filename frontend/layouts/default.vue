<template>
  <div class="relative flex min-h-screen flex-col bg-background">
    <NuxtLink to="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[100] focus:bg-foreground focus:text-background focus:px-4 focus:py-2 focus:rounded focus:font-label focus:text-sm">Skip to content</NuxtLink>
    <LoadingBar />
    <AppHeader />
    <main id="main" class="flex-1">
      <div class="mx-auto max-w-[960px] px-5 py-6 md:py-8 xl:max-w-[1280px]">
        <slot />
      </div>
    </main>
    <AppFooter />
    <CookieConsent />
  </div>
</template>
