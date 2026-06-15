<template>
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
            <li><a href="#" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">About</a></li>
            <li><a href="#" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Contact</a></li>
            <li><a href="#" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Careers</a></li>
          </ul>
        </div>
        <div>
          <h4 class="font-label text-xs font-bold uppercase tracking-[0.062em] mb-3">Legal</h4>
          <ul class="space-y-1.5">
            <li><a href="#" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Privacy Policy</a></li>
            <li><a href="#" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Terms of Use</a></li>
            <li><a href="#" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Cookie Notice</a></li>
          </ul>
        </div>
        <div>
          <h4 class="font-label text-xs font-bold uppercase tracking-[0.062em] mb-3">Follow</h4>
          <ul class="space-y-1.5">
            <li><a href="#" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Twitter / X</a></li>
            <li><a href="#" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">Facebook</a></li>
            <li><a href="#" class="font-label text-xs text-muted-foreground hover:text-foreground transition-colors">LinkedIn</a></li>
          </ul>
        </div>
      </div>
      <div class="mt-8 border-t border-border pt-6 flex flex-col sm:flex-row items-center justify-between gap-2">
        <p class="font-label text-xs text-muted-foreground">
          &copy; {{ new Date().getFullYear() }} The AI Journal. All rights reserved.
        </p>
        <p class="font-label text-xs text-muted-foreground">
          Built by <a href="https://www.technioz.com" target="_blank" class="underline underline-offset-4 hover:text-foreground">Technioz</a>
        </p>
      </div>
    </div>
  </footer>
</template>
