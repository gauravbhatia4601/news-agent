<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<script setup>
useHead({
  script: [
    {
      src: 'https://umami.technioz.com/script.js',
      defer: true,
      'data-website-id': '3a676a6d-3b90-4a60-acb1-6cd8f75d0601'
    }
  ]
})
</script>