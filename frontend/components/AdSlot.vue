<script setup lang="ts">
const props = withDefaults(defineProps<{
  variant?: 'horizontal' | 'vertical' | 'inline'
  label?: string
}>(), {
  variant: 'horizontal',
  label: 'Advertisement',
})

const adClient = useRuntimeConfig().public.adsenseClient as string || ''
const adSlotId = useRuntimeConfig().public.adsenseSlot as string || ''
const loaded = ref(false)
const observer = ref<IntersectionObserver | null>(null)
const container = ref<HTMLElement | null>(null)

onMounted(() => {
  if (!adClient || !adSlotId) return
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
  <div
    ref="container"
    class="ad-slot flex items-center justify-center my-6"
    :class="[
      sizeClass,
      variant === 'vertical' ? 'mx-auto w-full' : 'mx-auto w-full',
    ]"
  >
    <!-- AdSense ad (only when configured) -->
    <ins
      v-if="adClient && adSlotId && loaded"
      class="adsbygoogle"
      style="display:block"
      :data-ad-client="adClient"
      :data-ad-slot="adSlotId"
      :data-ad-format="variant === 'vertical' ? 'vertical' : 'auto'"
      data-full-width-responsive="true"
    />

    <!-- Placeholder (shown when AdSense not yet configured or not loaded) -->
    <div
      v-if="!adClient || !adSlotId || !loaded"
      class="flex items-center justify-center w-full h-full border border-dashed border-border bg-muted/30"
      :class="sizeClass"
    >
      <span class="font-label text-[10px] uppercase tracking-[0.15em] text-muted-foreground/50">{{ label }}</span>
    </div>
  </div>
</template>