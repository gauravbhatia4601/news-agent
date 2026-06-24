<script setup lang="ts">
const nuxtApp = useNuxtApp()
const loading = ref(false)

nuxtApp.hook('page:start', () => {
  loading.value = true
})

nuxtApp.hook('page:finish', () => {
  loading.value = false
})
</script>

<template>
  <div
    v-if="loading"
    class="fixed top-0 left-0 right-0 z-[9999] h-[3px] bg-amber-500/20 overflow-hidden"
  >
    <div class="h-full bg-amber-500 animate-loading-bar" />
  </div>
</template>

<style scoped>
@keyframes loading-bar {
  0% { width: 0%; margin-left: 0; }
  50% { width: 60%; margin-left: 0; }
  100% { width: 100%; margin-left: 100%; }
}

.animate-loading-bar {
  animation: loading-bar 1.5s ease-in-out infinite;
}
</style>