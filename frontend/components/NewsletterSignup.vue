<script setup lang="ts">
const props = withDefaults(defineProps<{
  variant?: 'card' | 'inline'
}>(), {
  variant: 'card',
})

const email = ref('')
const status = ref<'idle' | 'loading' | 'success' | 'error'>('idle')
const message = ref('')

async function handleSubmit() {
  if (!email.value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    status.value = 'error'
    message.value = 'Please enter a valid email address.'
    return
  }

  status.value = 'loading'
  message.value = ''

  try {
    const api = useNewsApi()
    const res = await api.subscribe(email.value, props.variant === 'card' ? 'footer' : 'inline')
    status.value = 'success'
    message.value = res.message || 'Subscribed! Watch your inbox for the latest AI-powered news.'
    email.value = ''
  } catch (e: any) {
    status.value = 'error'
    message.value = e?.data?.message || e?.data?.errors?.email?.[0] || 'Something went wrong. Please try again.'
  }
}
</script>

<template>
  <div v-if="variant === 'card'" class="border-t-strong bg-foreground text-background">
    <div class="mx-auto max-w-[960px] px-5 py-10 xl:max-w-[1280px]">
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div class="max-w-md">
          <h3 class="font-display text-xl font-bold text-background tracking-tight mb-1">Stay ahead with The AI Journal</h3>
          <p class="font-serif text-sm text-background/70">AI-powered news from India and the world. Delivered to your inbox. No spam, ever.</p>
        </div>
        <form @submit.prevent="handleSubmit" class="flex gap-2 w-full md:w-auto md:min-w-[360px]">
          <input
            v-model="email"
            type="email"
            placeholder="you@example.com"
            class="flex-1 h-11 px-4 bg-background/10 border border-background/20 text-background placeholder-background/50 font-label text-sm focus:outline-none focus:border-background/40 transition-colors"
            :disabled="status === 'loading' || status === 'success'"
          />
          <button
            type="submit"
            :disabled="status === 'loading' || status === 'success'"
            class="h-11 px-6 bg-secondary text-secondary-foreground font-label text-xs font-bold uppercase tracking-[0.062em] hover:opacity-90 transition-opacity disabled:opacity-50 whitespace-nowrap"
          >
            {{ status === 'loading' ? '...' : status === 'success' ? 'Done' : 'Subscribe' }}
          </button>
        </form>
      </div>
      <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0">
        <p v-if="message" class="mt-3 font-label text-xs" :class="status === 'success' ? 'text-background/80' : 'text-secondary'">{{ message }}</p>
      </Transition>
    </div>
  </div>

  <div v-else class="w-full">
    <form @submit.prevent="handleSubmit" class="flex gap-2">
      <input
        v-model="email"
        type="email"
        placeholder="you@example.com"
        class="flex-1 h-10 px-4 bg-muted border border-border text-foreground placeholder-muted-foreground font-label text-sm focus:outline-none focus:border-foreground/30 transition-colors"
        :disabled="status === 'loading' || status === 'success'"
      />
      <button
        type="submit"
        :disabled="status === 'loading' || status === 'success'"
        class="h-10 px-5 bg-foreground text-background font-label text-xs font-bold uppercase tracking-[0.062em] hover:opacity-90 transition-opacity disabled:opacity-50 whitespace-nowrap"
      >
        {{ status === 'loading' ? '...' : status === 'success' ? 'Done' : 'Subscribe' }}
      </button>
    </form>
    <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0">
      <p v-if="message" class="mt-2 font-label text-xs" :class="status === 'success' ? 'text-muted-foreground' : 'text-red-600'">{{ message }}</p>
    </Transition>
  </div>
</template>