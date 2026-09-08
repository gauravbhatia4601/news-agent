<template>
  <div class="space-y-6">
    <!-- LLM Configuration -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-admin-sidebar/5 flex items-center justify-center">
          <Settings class="w-5 h-5 text-admin-accent" />
        </div>
        <div>
          <h2 class="text-[15px] font-semibold text-admin-text">LLM Configuration</h2>
          <p class="text-xs text-admin-muted">Provider and model used for article generation</p>
        </div>
      </div>

      <div v-if="loading" class="flex items-center justify-center py-12">
        <Loader2 class="w-6 h-6 text-slate-400 animate-spin" />
      </div>

      <div v-else class="space-y-5">
        <!-- Provider -->
        <div>
          <label class="block text-[11px] font-semibold uppercase text-admin-muted tracking-wider mb-2">Primary Provider</label>
          <select
            v-model="form.provider"
            class="w-full px-4 py-2.5 text-sm bg-admin-surface border border-admin-border rounded-[14px] focus-ring transition-all"
          >
            <option v-for="p in providers" :key="p.value" :value="p.value">
              {{ p.label }}{{ p.has_key ? '' : ' (no API key)' }}
            </option>
          </select>
          <p v-if="selectedProvider && !selectedProvider.has_key" class="mt-1.5 text-xs text-admin-accent">
            No API key configured for this provider. Set it in .env to use it.
          </p>
        </div>

        <!-- Model -->
        <div>
          <label class="block text-[11px] font-semibold uppercase text-admin-muted tracking-wider mb-2">Primary Model</label>
          <input
            v-model="form.model"
            type="text"
            placeholder="e.g. gemma4:31b-cloud, anthropic/claude-sonnet-4.5"
            class="w-full px-4 py-2.5 text-sm bg-admin-surface border border-admin-border rounded-[14px] focus-ring transition-all"
          />
          <p class="mt-1.5 text-xs text-slate-400">Enter the model ID for the selected provider.</p>
        </div>

        <!-- Timeout -->
        <div>
          <label class="block text-[11px] font-semibold uppercase text-admin-muted tracking-wider mb-2">Timeout (seconds)</label>
          <input
            v-model.number="form.timeout"
            type="number"
            min="30"
            max="1800"
            class="w-full px-4 py-2.5 text-sm bg-admin-surface border border-admin-border rounded-[14px] focus-ring transition-all"
          />
        </div>

        <!-- Enabled toggle -->
        <div class="flex items-center justify-between py-2">
          <div>
            <span class="text-sm font-medium text-admin-text">Generation Enabled</span>
            <p class="text-xs text-admin-muted">Allow the engine to generate new articles</p>
          </div>
          <button
            @click="form.enabled = !form.enabled"
            class="relative w-11 h-6 rounded-full transition-colors duration-200"
            :class="form.enabled ? 'bg-admin-sidebar' : 'bg-slate-200'"
          >
            <span
              class="absolute top-0.5 left-0.5 w-5 h-5 bg-admin-surface rounded-full shadow-sm transition-transform duration-200"
              :class="form.enabled ? 'translate-x-5' : ''"
            />
          </button>
        </div>
      </div>
    </div>

    <!-- Fallback Configuration -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
          <Zap class="w-5 h-5 text-admin-accent" />
        </div>
        <div>
          <h2 class="text-[15px] font-semibold text-admin-text">Fallback Model</h2>
          <p class="text-xs text-admin-muted">Used when the primary model fails (optional)</p>
        </div>
      </div>

      <div v-if="!loading" class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
          <label class="block text-[11px] font-semibold uppercase text-admin-muted tracking-wider mb-2">Fallback Provider</label>
          <select
            v-model="form.fallback_provider"
            class="w-full px-4 py-2.5 text-sm bg-admin-surface border border-admin-border rounded-[14px] focus-ring transition-all"
          >
            <option value="">None</option>
            <option v-for="p in providers" :key="p.value" :value="p.value">
              {{ p.label }}{{ p.has_key ? '' : ' (no API key)' }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-semibold uppercase text-admin-muted tracking-wider mb-2">Fallback Model</label>
          <input
            v-model="form.fallback_model"
            type="text"
            placeholder="e.g. gemma4:31b-cloud"
            class="w-full px-4 py-2.5 text-sm bg-admin-surface border border-admin-border rounded-[14px] focus-ring transition-all"
          />
        </div>
      </div>
    </div>

    <!-- Save Button -->
    <div v-if="!loading" class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div v-if="saved" class="flex items-center gap-1.5 text-sm text-emerald-600">
          <Check class="w-4 h-4" />
          <span>Saved</span>
        </div>
        <div v-if="error" class="text-sm text-red-500">{{ error }}</div>
      </div>
      <button
        @click="saveSettings"
        :disabled="saving"
        class="flex items-center gap-2 px-5 py-2.5 bg-admin-sidebar hover:bg-slate-800 text-white text-sm font-medium rounded-[14px] transition-colors disabled:opacity-50"
      >
        <Loader2 v-if="saving" class="w-4 h-4 animate-spin" />
        <span>{{ saving ? 'Saving...' : 'Save Settings' }}</span>
      </button>
    </div>

    <!-- Quality Gate (unchanged read-only) -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
          <ShieldCheck class="w-5 h-5 text-emerald-600" />
        </div>
        <div>
          <h2 class="text-[15px] font-semibold text-admin-text">Quality Gate</h2>
          <p class="text-xs text-admin-muted">Automated article quality thresholds</p>
        </div>
      </div>
      <div class="space-y-3">
        <div v-for="item in qualityItems" :key="item.label" class="flex items-center justify-between py-3 border-b border-admin-border last:border-0">
          <span class="text-sm text-admin-muted">{{ item.label }}</span>
          <span class="text-sm font-medium text-admin-text">{{ item.value }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Settings, ShieldCheck, Zap, Loader2, Check } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()

const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const error = ref('')
const providers = ref<any[]>([])

const form = reactive({
  provider: '',
  model: '',
  timeout: 300,
  enabled: true,
  fallback_provider: '',
  fallback_model: '',
})

const selectedProvider = computed(() => providers.value.find(p => p.value === form.provider))

async function loadSettings() {
  try {
    const res = await api.getSettings()
    const data = res.data
    providers.value = data.providers
    const s = data.settings
    form.provider = s.provider
    form.model = s.model
    form.timeout = s.timeout
    form.enabled = s.enabled
    form.fallback_provider = s.fallback_provider
    form.fallback_model = s.fallback_model
  } catch (e: any) {
    error.value = e?.data?.message || 'Failed to load settings'
  }
  loading.value = false
}

async function saveSettings() {
  saving.value = true
  error.value = ''
  saved.value = false
  try {
    await api.updateSettings({
      provider: form.provider,
      model: form.model,
      timeout: form.timeout,
      enabled: form.enabled,
      fallback_provider: form.fallback_provider,
      fallback_model: form.fallback_model,
    })
    saved.value = true
    setTimeout(() => { saved.value = false }, 3000)
  } catch (e: any) {
    error.value = e?.data?.message || 'Failed to save settings'
  }
  saving.value = false
}

onMounted(loadSettings)

const qualityItems = [
  { label: 'Minimum Words', value: '400' },
  { label: 'Minimum Sections', value: '3' },
  { label: 'Minimum Sources', value: '2' },
  { label: 'Active Voice Ratio', value: '≥ 45%' },
  { label: 'Hook Quality', value: 'Required' },
]
</script>