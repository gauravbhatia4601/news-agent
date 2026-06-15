<template>
  <div class="min-h-screen bg-slate-900 flex items-center justify-center p-4">
    <div class="w-full max-w-[400px]">
      <div class="text-center mb-10">
        <div class="inline-flex items-center gap-2 mb-2">
          <Shield class="w-6 h-6 text-blue-400" />
          <span class="text-white font-display text-xl font-bold">The AI Journal</span>
        </div>
        <p class="text-slate-500 text-sm">Admin Panel</p>
      </div>

      <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl">
        <div class="px-6 pt-6 pb-2">
          <h2 class="text-white font-semibold">Sign in</h2>
          <p class="text-slate-400 text-sm mt-1">Enter your credentials to continue</p>
        </div>

        <form @submit.prevent="handleLogin" class="p-6 space-y-4">
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
          >
            <div v-if="error" class="flex items-start gap-2 bg-red-500/10 border border-red-500/20 rounded-md px-3 py-2">
              <AlertCircle class="w-4 h-4 text-red-400 shrink-0 mt-0.5" />
              <p class="text-xs text-red-300">{{ error }}</p>
            </div>
          </Transition>

          <div>
            <label class="block text-xs font-medium text-slate-400 mb-1.5">Email</label>
            <input
              v-model="email"
              type="email"
              required
              class="w-full bg-slate-900 border border-slate-600 rounded-md px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
              placeholder="admin@thetrustjournal.com"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-400 mb-1.5">Password</label>
            <div class="relative">
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                class="w-full bg-slate-900 border border-slate-600 rounded-md px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 pr-10"
                placeholder="Enter password"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300"
              >
                <Eye v-if="!showPassword" class="w-4 h-4" />
                <EyeOff v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="w-full bg-blue-600 text-white font-medium py-2.5 rounded-md text-sm hover:bg-blue-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            <Loader2 v-if="loading" class="animate-spin w-4 h-4" />
            <span>{{ loading ? 'Signing in...' : 'Sign In' }}</span>
          </button>
        </form>
      </div>

      <p class="text-center mt-6 text-xs text-slate-600">
        Default: admin@thetrustjournal.com / changeme123
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Shield, AlertCircle, Eye, EyeOff, Loader2 } from 'lucide-vue-next'

definePageMeta({ layout: false })

const { login, isAuthenticated } = useAdminAuth()
const router = useRouter()

const email = ref('admin@thetrustjournal.com')
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