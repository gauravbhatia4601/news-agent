<template>
  <div class="min-h-screen bg-[#F8FAFC] flex items-center justify-center p-4">
    <div class="w-full max-w-[400px]">
      <div class="text-center mb-10">
        <NuxtLink to="/" target="_blank" class="inline-flex flex-col items-center gap-3"
        >
          <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="48" height="48" rx="14" fill="#1a2233"/>
            <path d="M18 12L18 36M18 12L28 18.5M18 12L28 5.5" stroke="#f5a623" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M24 16C25.5 18.5 28 21 31 22.5M31 22.5L27 18.5M31 22.5L29 26.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="34" cy="32" r="3" stroke="white" stroke-width="1.5"/>
          </svg>
          <div>
            <span class="text-slate-900 font-semibold text-xl tracking-tight block">The Neural Journal</span>
            <span class="text-slate-400 text-xs uppercase tracking-[0.2em] font-medium">Admin Console</span>
          </div>
        </NuxtLink>
      </div>

      <div class="bg-white border border-slate-200 rounded-[14px] overflow-hidden shadow-sm">
        <div class="px-8 pt-8 pb-2">
          <h2 class="text-slate-900 font-semibold text-lg">Welcome back</h2>
          <p class="text-slate-500 text-sm mt-1">Enter credentials to access the dashboard</p>
        </div>

        <form @submit.prevent="handleLogin" class="p-8 pt-4 space-y-5">
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
          >
            <div v-if="error" class="flex items-start gap-3 bg-red-50 border border-red-100 rounded-[14px] px-4 py-3">
              <AlertCircle class="w-4 h-4 text-red-500 shrink-0 mt-0.5" />
              <p class="text-sm text-red-700 leading-relaxed">{{ error }}</p>
            </div>
          </Transition>

          <div class="space-y-1.5">
            <label class="block text-xs font-medium text-slate-500">Email address</label>
            <input v-model="email" type="email" required autofocus
              class="w-full bg-slate-50 border border-slate-200 rounded-[14px] px-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-400 transition-all"
              placeholder="admin@theneuraljournal.com"
            />
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-medium text-slate-500">Password</label>
            <div class="relative">
              <input v-model="password" :type="showPassword ? 'text' : 'password'" required
                class="w-full bg-slate-50 border border-slate-200 rounded-[14px] px-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-400 transition-all pr-11"
                placeholder="Enter your password"
              />
              <button type="button" @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
              >
                <Eye v-if="!showPassword" class="w-4 h-4" />
                <EyeOff v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <button type="submit" :disabled="loading"
            class="w-full bg-slate-900 hover:bg-slate-800 text-white font-medium py-3 rounded-[14px] text-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            <Loader2 v-if="loading" class="animate-spin w-4 h-4" />
            <span v-else>Sign in</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
h1, h2, h3, h4, h5, h6 {
  font-family: 'Inter', sans-serif;
  letter-spacing: -0.025em;
}
</style>

<script setup lang="ts">
import { AlertCircle, Eye, EyeOff, Loader2 } from 'lucide-vue-next'

definePageMeta({ layout: false })

const { login, isAuthenticated } = useAdminAuth()
const router = useRouter()
const route = useRoute()

const email = ref('admin@theneuraljournal.com')
const password = ref('')
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)

onMounted(() => {
  if (isAuthenticated.value) {
    const redirect = (route.query.redirect as string) || '/admin'
    router.push(redirect)
  }
})

async function handleLogin() {
  loading.value = true
  error.value = ''
  try {
    await login(email.value, password.value)
    const redirect = (route.query.redirect as string) || '/admin'
    router.push(redirect)
  } catch (e: any) {
    error.value = e?.data?.message || (e?.data?.errors ? Object.values(e.data.errors).flat().join(' ') : 'Invalid credentials')
  } finally {
    loading.value = false
  }
}
</script>