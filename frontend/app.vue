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
      'data-website-id': 'a6df10a3-8111-494c-b2c8-1d72e86c4f92'
    }
  ]
})
</script>