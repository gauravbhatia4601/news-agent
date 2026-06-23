<script setup lang="ts">
const props = withDefaults(defineProps<{
  variant?: 'horizontal' | 'vertical' | 'inline'
  label?: string
}>(), {
  variant: 'horizontal',
  label: 'Advertisement',
})

const config = useRuntimeConfig()
const adClient = String(config.public.adsenseClient || '')
const adSlotId = String(config.public.adsenseSlot || '')
const enabled = computed(() => !!adClient && !!adSlotId)
const loaded = ref(false)
const observer = ref<IntersectionObserver | null>(null)
const container = ref<HTMLElement | null>(null)

onMounted(() => {
  if (!enabled.value) return
  if (props.variant === 'inline') return

  observer.value = new IntersectionObserver(
    (entries) => {
      if (entries[0]?.isIntersecting && !loaded.value) {
        loaded.value = true
        loadAd()
      }
    },
    { rootMargin: '200px' }
  )

  if (container.value) {
    observer.value.observe(container.value)
  }
})

onUnmounted(() => {
  observer.value?.disconnect()
})

function loadAd() {
  if (typeof window === 'undefined') return
  try {
    // @ts-ignore
    ;(window.adsbygoogle = window.adsbygoogle || []).push({})
  } catch {}
}

const sizeClass = computed(() => {
  if (props.variant === 'vertical') return 'min-h-[600px] max-w-[300px]'
  if (props.variant === 'inline') return 'min-h-[100px]'
  return 'min-h-[90px] max-w-[970px]'
})
</script>

<template>
  <div v-if="enabled" ref="container" class="ad-slot flex items-center justify-center my-6 mx-auto w-full" :class="sizeClass">
    <ins
      v-if="loaded"
      class="adsbygoogle"
      style="display:block"
      :data-ad-client="adClient"
      :data-ad-slot="adSlotId"
      :data-ad-format="variant === 'vertical' ? 'vertical' : 'auto'"
      data-full-width-responsive="true"
    />
  </div>
</template>