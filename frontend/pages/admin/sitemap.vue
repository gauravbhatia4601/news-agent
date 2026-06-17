<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-slate-900/5 flex items-center justify-center"><Map class="w-5 h-5 text-amber-600" /></div>
        <div>
          <h1 class="text-[18px] font-semibold text-slate-900">Sitemaps</h1>
          <p class="text-xs text-slate-500">Manage XML sitemap files for SEO</p>
        </div>
      </div>
      <button @click="regenerate" :disabled="generating" class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-[14px] text-sm font-medium transition-all disabled:opacity-50 flex items-center gap-2"
      >
        <Loader2 v-if="generating" class="w-4 h-4 animate-spin" />
        <RefreshCw v-else class="w-4 h-4" />
        {{ generating ? 'Generating...' : 'Regenerate Sitemap' }}
      </button>
    </div>

    <!-- Alert -->
    <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0"
    >
      <div v-if="alert" class="p-4 rounded-[14px] text-sm flex items-start gap-2" :class="alert.type === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'"
      >
        <CheckCircle v-if="alert.type === 'success'" class="w-4 h-4 shrink-0 mt-0.5" />
        <AlertCircle v-else class="w-4 h-4 shrink-0 mt-0.5" />
        {{ alert.text }}
      </div>
    </Transition>

    <!-- Viewer Modal -->
    <div v-if="viewer" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="viewer = null"
    >
      <div class="bg-white rounded-[14px] border border-slate-200 shadow-xl w-full max-w-4xl max-h-[85vh] flex flex-col">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
          <div class="flex items-center gap-3">
            <FileCode2 class="w-5 h-5 text-slate-400" />
            <h3 class="text-sm font-semibold text-slate-900">{{ viewer.name }}</h3>
            <span class="text-xs text-slate-400">{{ viewer.size_human }}</span>
          </div>
          <div class="flex items-center gap-2">
            <a :href="viewer.url" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-lg border border-slate-200 transition-colors"
            >
              <ExternalLink class="w-3.5 h-3.5" /> Open XML
            </a>
            <button @click="viewer = null" class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-700 transition-colors"
            ><X class="w-4 h-4" /></button>
          </div>
        </div>
        <div class="flex-1 overflow-auto p-6 bg-slate-50">
          <pre class="text-xs leading-relaxed text-slate-700 font-mono whitespace-pre-wrap break-all">{{ viewer.content }}</pre>
        </div>
      </div>
    </div>

    <!-- Files Table -->
    <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-[15px] font-semibold text-slate-900">Sitemap Files</h2>
        <span class="text-xs text-slate-400">{{ files.length }} file{{ files.length === 1 ? '' : 's' }}</span>
      </div>
      <div v-if="loading" class="p-12 text-center">
        <Loader2 class="w-8 h-8 text-slate-400 animate-spin mx-auto mb-3" />
        <p class="text-sm text-slate-500">Loading sitemaps...</p>
      </div>
      <div v-else-if="!files.length" class="p-12 text-center">
        <Map class="w-8 h-8 text-slate-300 mx-auto mb-3" />
        <p class="text-sm text-slate-500">No sitemap files found</p>
        <p class="text-xs text-slate-400 mt-1">Click "Regenerate Sitemap" to create them</p>
      </div>
      <div v-else class="divide-y divide-slate-100">
        <div v-for="f in files" :key="f.name" class="px-6 py-3.5 flex items-center gap-4 hover:bg-slate-50 transition-colors"
        >
          <FileCode2 class="w-5 h-5 text-slate-400 shrink-0" />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-slate-900 truncate">{{ f.name }}</p>
            <p class="text-xs text-slate-400">{{ f.size_human }} · Modified {{ fmtTime(f.modified_at) }}</p>
          </div>
          <div class="flex items-center gap-2">
            <button @click="openViewer(f.name)" class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-lg border border-slate-200 transition-colors"
            >View</button>
            <a :href="f.url" target="_blank" class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-lg border border-slate-200 transition-colors inline-flex items-center gap-1.5"
            >
              <ExternalLink class="w-3.5 h-3.5" /> XML
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Loader2, RefreshCw, Map, FileCode2, ExternalLink, X, CheckCircle, AlertCircle } from 'lucide-vue-next'
import { useAdminApi } from '~/composables/useAdminApi'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const files = ref<any[]>([])
const loading = ref(true)
const generating = ref(false)
const alert = ref<{ type: string; text: string } | null>(null)
const viewer = ref<any>(null)

async function load() {
  loading.value = true
  try {
    const res = await api.getSitemaps()
    files.value = res.data
  } catch (e: any) {
    alert.value = { type: 'error', text: e?.data?.message || 'Failed to load sitemaps' }
  }
  loading.value = false
}

async function regenerate() {
  generating.value = true
  alert.value = null
  try {
    const res = await api.regenerateSitemap()
    alert.value = { type: 'success', text: 'Sitemaps regenerated: ' + res.data.files.join(', ') }
    await load()
  } catch (e: any) {
    alert.value = { type: 'error', text: e?.data?.message || 'Failed to regenerate sitemap' }
  }
  generating.value = false
}

async function openViewer(name: string) {
  try {
    const res = await api.getSitemap(name)
    viewer.value = res.data
  } catch (e: any) {
    alert.value = { type: 'error', text: e?.data?.message || 'Failed to load sitemap content' }
  }
}

function fmtTime(iso: string) {
  const d = new Date(iso)
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(load)
</script>
