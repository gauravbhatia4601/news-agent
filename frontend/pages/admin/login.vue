<template>
  <div class="min-h-screen bg-[#0f1115] flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Subtle background pattern -->
    <div class="absolute inset-0 opacity-[0.03]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 40px 40px;" />

    <div class="w-full max-w-[400px] relative z-10">
      <!-- Brand -->
      <div class="text-center mb-10">
        <NuxtLink to="/" target="_blank" class="inline-flex flex-col items-center gap-3 group">
          <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" class="transition-transform duration-300 group-hover:scale-105">
            <rect width="48" height="48" rx="14" fill="#1a2233" stroke="#2a3245" stroke-width="1"/>
            <path d="M18 12L18 36M18 12L28 18.5M18 12L28 5.5" stroke="#f5a623" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M24 16C25.5 18.5 28 21 31 22.5M31 22.5L27 18.5M31 22.5L29 26.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="34" cy="32" r="3" stroke="white" stroke-width="1.5"/>
          </svg>
          <div>
            <span class="text-white font-display text-xl font-bold tracking-tight block">The AI Journal</span>
            <span class="text-[#6b7080] text-xs uppercase tracking-[0.2em] font-medium">Admin Console</span>
          </div>
        </NuxtLink>
      </div>

      <!-- Card -->
      <div class="bg-[#161822] border border-white/[0.06] rounded-2xl shadow-2xl shadow-black/20 overflow-hidden">
        <div class="px-8 pt-8 pb-2">
          <h2 class="text-white font-medium text-lg">Welcome back</h2>
          <p class="text-[#6b7080] text-sm mt-1">Enter your credentials to access the dashboard</p>
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
            <div v-if="error" class="flex items-start gap-3 bg-red-500/[0.08] border border-red-500/20 rounded-xl px-4 py-3">
              <AlertCircle class="w-4 h-4 text-red-400 shrink-0 mt-0.5" />
              <p class="text-sm text-red-300 leading-relaxed">{{ error }}</p>
            </div>
          </Transition>

          <div class="space-y-1.5">
            <label class="block text-xs font-medium text-[#8b8f9e] tracking-wide">Email address</label>
            <input
              v-model="email"
              type="email"
              required
              autofocus
              class="w-full bg-[#0f1115] border border-white/[0.08] rounded-xl px-4 py-3 text-sm text-white placeholder-[#4b4e5c] focus:outline-none focus:border-[#f5a623]/50 focus:ring-1 focus:ring-[#f5a623]/20 transition-all duration-200"
              placeholder="admin@theaijournal.com"
            />
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-medium text-[#8b8f9e] tracking-wide">Password</label>
            <div class="relative">
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                class="w-full bg-[#0f1115] border border-white/[0.08] rounded-xl px-4 py-3 text-sm text-white placeholder-[#4b4e5c] focus:outline-none focus:border-[#f5a623]/50 focus:ring-1 focus:ring-[#f5a623]/20 transition-all duration-200 pr-11"
                placeholder="Enter your password"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#4b4e5c] hover:text-[#8b8f9e] transition-colors"
              >
                <Eye v-if="!showPassword" class="w-4 h-4" />
                <EyeOff v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="w-full bg-[#1a2233] border border-[#f5a623]/20 text-white font-medium py-3 rounded-xl text-sm hover:bg-[#1a2233]/80 hover:border-[#f5a623]/40 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 group"
          >
            <Loader2 v-if="loading" class="animate-spin w-4 h-4" />
            <span v-else class="group-hover:translate-x-0.5 transition-transform">Sign in</span>
            <ArrowRight v-if="!loading" class="w-4 h-4 opacity-0 group-hover:opacity-100 -translate-x-2 group-hover:translate-x-0 transition-all" />
          </button>
        </form>
      </div>

      <p class="text-center mt-8 text-[11px] text-[#4b4e5c]">
        Default: admin@theaijournal.com / changeme123
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Shield, AlertCircle, Eye, EyeOff, Loader2, ArrowRight } from 'lucide-vue-next'

definePageMeta({ layout: false })

const { login, isAuthenticated } = useAdminAuth()
const router = useRouter()

const email = ref('admin@theaijournal.com')
const password = ref('')
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)

onMounted(() => {
  const { initFromStorage } = useAdminAuth()
  initFromStorage()
  if (isAuthenticated.value) {
    router.push('/admin')
  }
})

async function handleLogin() {
  loading.value = true
  error.value = ''
  try {
    await login(email.value, password.value)
    router.push('/admin')
  } catch (e: any) {
    error.value = e?.data?.message || (e?.data?.errors ? Object.values(e.data.errors).flat().join(' ') : 'Invalid credentials')
  } finally {
    loading.value = false
  }
}
</script>