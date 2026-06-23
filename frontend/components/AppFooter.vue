<template>
  <NewsletterSignup variant="card" />
  <footer class="border-t-strong mt-16">
    <div class="mx-auto max-w-[960px] px-5 py-10 xl:max-w-[1280px]">
      <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
        <div>
          <h4 class="font-label text-xs font-bold uppercase tracking-[0.062em] mb-3">Sections</h4>
          <ul class="space-y-1.5">
            <li><NuxtLink to="/" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Home</NuxtLink></li>
            <li><NuxtLink to="/trending" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Trending</NuxtLink></li>
            <li><NuxtLink to="/categories" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Topics</NuxtLink></li>
          </ul>
        </div>
        <div>
          <h4 class="font-label text-xs font-bold uppercase tracking-[0.062em] mb-3">Company</h4>
          <ul class="space-y-1.5">
            <li><NuxtLink to="/about" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">About</NuxtLink></li>
            <li><NuxtLink to="/contact" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Contact</NuxtLink></li>
          </ul>
        </div>
        <div>
          <h4 class="font-label text-xs font-bold uppercase tracking-[0.062em] mb-3">Legal</h4>
          <ul class="space-y-1.5">
            <li><NuxtLink to="/privacy" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Privacy Policy</NuxtLink></li>
            <li><NuxtLink to="/terms" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Terms of Use</NuxtLink></li>
            <li><NuxtLink to="/cookies" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Cookie Notice</NuxtLink></li>
          </ul>
        </div>
        <div>
          <h4 class="font-label text-xs font-bold uppercase tracking-[0.062em] mb-3">Follow</h4>
          <ul class="space-y-1.5">
            <li><a href="https://x.com/theneuraljournal" target="_blank" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">X / Twitter</a></li>
            <li><a href="https://linkedin.com/company/theneuraljournal" target="_blank" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">LinkedIn</a></li>
          </ul>
        </div>
      </div>
      <div class="mt-8 border-t border-border pt-6 flex flex-col sm:flex-row items-center justify-between gap-2">
        <p class="font-label text-xs text-muted-foreground">
          &copy; {{ new Date().getFullYear() }} The Neural Journal. All rights reserved.
        </p>
        <p class="font-label text-xs text-muted-foreground">
          Built by <a href="https://www.technioz.com" target="_blank" class="underline underline-offset-4 hover:text-foreground">Technioz</a>
        </p>
      </div>
    </div>
  </footer>
</template>
