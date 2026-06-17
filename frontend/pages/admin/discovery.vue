<template>
  <div class="space-y-6">
    <!-- Trigger Discovery Card -->
    <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <div class="flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-slate-900/5 flex items-center justify-center shrink-0"><Search class="w-5 h-5 text-amber-600" /></div>
        <div class="flex-1">
          <h2 class="text-[15px] font-semibold text-slate-900">Trigger Discovery</h2>
          <p class="text-[13px] text-slate-500 mt-1">Manually trigger a news discovery run across all categories.</p>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
            <div class="space-y-1.5">
              <label class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Topics per category</label>
              <input v-model.number="discoveryParams.limit" type="number" min="1" max="20" class="w-full bg-slate-50 border border-slate-200 rounded-[14px] px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-slate-400 transition-colors" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Freshness (hours)</label>
              <input v-model.number="discoveryParams.fresh_hours" type="number" min="1" max="48" class="w-full bg-slate-50 border border-slate-200 rounded-[14px] px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-slate-400 transition-colors" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Sources per topic</label>
              <input v-model.number="discoveryParams.sources_per_topic" type="number" min="2" max="6" class="w-full bg-slate-50 border border-slate-200 rounded-[14px] px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-slate-400 transition-colors" />
            </div>
          </div>

          <button @click="triggerDiscovery" :disabled="discovering" class="mt-6 px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-[14px] text-sm font-medium transition-all disabled:opacity-50 flex items-center gap-2"
          >
            <Loader2 v-if="discovering" class="w-4 h-4 animate-spin" />
            <Play v-else class="w-4 h-4" />
            {{ discovering ? 'Discovering...' : 'Start Discovery' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Discovery Result -->
    <div v-if="discoveryResult" class="bg-white rounded-[14px] border border-emerald-200 overflow-hidden shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div class="px-6 py-4 border-b border-emerald-200 bg-emerald-50 flex items-center gap-2">
        <CheckCircle class="w-5 h-5 text-emerald-600" />
        <h2 class="text-[15px] font-semibold text-emerald-800">Discovery Complete</h2>
      </div>
      <div class="p-6">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 mb-6">
          <div class="text-center">
            <p class="text-3xl font-bold text-slate-900">{{ discoveryResult.discovered }}</p>
            <p class="text-xs text-slate-500 mt-1">Discovered</p>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold text-emerald-600">{{ discoveryResult.queued_for_generation }}</p>
            <p class="text-xs text-slate-500 mt-1">Queued</p>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold text-slate-900">{{ discoveryResult.skipped_existing || 0 }}</p>
            <p class="text-xs text-slate-500 mt-1">Existing (skipped)</p>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold text-slate-900">{{ discoveryResult.duration_seconds || 0 }}s</p>
            <p class="text-xs text-slate-500 mt-1">Duration</p>
          </div>
        </div>

        <div v-if="discoveryResult.topics?.length">
          <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider mb-3">New Topics</p>
          <div class="space-y-2">
            <div v-for="t in discoveryResult.topics.slice(0, 8)" :key="t.signature" class="flex items-center justify-between p-3 bg-slate-50 rounded-[14px] border border-slate-200">
              <div class="flex items-center gap-3">
                <span class="text-xs px-2 py-0.5 rounded-md bg-slate-200 text-slate-700 font-medium">{{ t.category }}</span>
                <span class="text-sm text-slate-900">{{ t.name }}</span>
              </div>
              <span class="text-xs text-slate-500">{{ t.source_count }} sources</span>
            </div>
            <p v-if="discoveryResult.topics.length > 8" class="text-xs text-slate-500 text-center">+{{ discoveryResult.topics.length - 8 }} more...</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Retry Failed -->
    <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <div class="flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center shrink-0"><RotateCcw class="w-5 h-5 text-amber-600" /></div>
        <div class="flex-1">
          <h2 class="text-[15px] font-semibold text-slate-900">Retry Failed Topics</h2>
          <p class="text-[13px] text-slate-500 mt-1">Reset all failed topics and queue them for regeneration.</p>
          <button @click="retryAllFailed" :disabled="retrying" class="mt-6 px-6 py-2.5 border border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-[14px] text-sm font-medium transition-all disabled:opacity-50 flex items-center gap-2"
          >
            <Loader2 v-if="retrying" class="w-4 h-4 animate-spin" />
            <RotateCcw v-else class="w-4 h-4" />
            {{ retrying ? 'Retrying...' : 'Retry All Failed' }}
          </button>
          <div v-if="retryResult" class="mt-4 p-4 bg-emerald-50 rounded-[14px] border border-emerald-200">
            <p class="text-sm text-emerald-700 font-medium">✓ Retried {{ retryResult.retried }} topics successfully.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Loader2, Search, Play, CheckCircle, RotateCcw } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const discovering = ref(false)
const retrying = ref(false)
const discoveryResult = ref<any>(null)
const retryResult = ref<any>(null)

const discoveryParams = reactive({ limit: 3, fresh_hours: 24, sources_per_topic: 3 })

async function triggerDiscovery() {
  discovering.value = true
  discoveryResult.value = null
  try {
    const res = await api.triggerDiscovery(discoveryParams)
    discoveryResult.value = res.data
  } catch (e: any) {
    alert('Discovery failed: ' + (e?.data?.message || 'Unknown error'))
  }
  discovering.value = false
}

async function retryAllFailed() {
  retrying.value = true
  retryResult.value = null
  try {
    const res = await api.retryFailed()
    retryResult.value = res.data
  } catch (e: any) {
    alert('Retry failed: ' + (e?.data?.message || 'Unknown error'))
  }
  retrying.value = false
}
</script>
