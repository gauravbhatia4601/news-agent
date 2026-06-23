<script setup lang="ts">
const consent = useCookie<'accepted' | 'declined' | null>('cookie-consent', {
  maxAge: 365 * 24 * 60 * 60,
  sameSite: 'lax',
})

const show = ref(false)

onMounted(() => {
  if (!consent.value) {
    show.value = true
  }
})

function accept() {
  consent.value = 'accepted'
  show.value = false
}

function decline() {
  consent.value = 'declined'
  show.value = false
}
</script>

<template>
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="opacity-0 translate-y-4"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 translate-y-4"
  >
    <div v-if="show" class="fixed bottom-0 left-0 right-0 z-50 px-4 pb-4 pointer-events-none">
      <div class="mx-auto max-w-[960px] xl:max-w-[1280px] pointer-events-auto">
        <div class="bg-foreground text-background rounded-lg shadow-xl border border-background/20 p-5 sm:p-6">
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
            <div class="flex-1 min-w-0">
              <h3 class="font-label text-sm font-bold uppercase tracking-[0.062em] mb-1.5">Cookie Notice</h3>
              <p class="font-serif text-sm text-background/80 leading-relaxed">
                We use privacy-first analytics (Umami) with no tracking cookies. If we add advertising in the future,
                cookies may be used to personalize ads. You can change your choice anytime in our
                <NuxtLink to="/cookies" class="underline underline-offset-2 hover:text-background">cookie policy</NuxtLink>.
              </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <button
                @click="decline"
                class="px-4 py-2 font-label text-xs font-bold uppercase tracking-[0.062em] text-background/70 border border-background/30 rounded hover:bg-background/10 transition-colors"
              >
                Decline
              </button>
              <button
                @click="accept"
                class="px-4 py-2 font-label text-xs font-bold uppercase tracking-[0.062em] text-foreground bg-background rounded hover:opacity-90 transition-opacity"
              >
                Accept
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>