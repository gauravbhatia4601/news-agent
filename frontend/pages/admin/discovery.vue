<template>
  <div class="space-y-6">
    <!-- Trigger Discovery Card -->
    <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-6">
      <div class="flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-[#1a2233]/40 flex items-center justify-center shrink-0"><Search class="w-5 h-5 text-[#f5a623]" /></div>
        <div class="flex-1">
          <h2 class="text-[15px] font-semibold text-white">Trigger Discovery</h2>
          <p class="text-[13px] text-[#94A3B8] mt-1">Manually trigger a news discovery run across all categories.</p>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
            <div class="space-y-1.5">
              <label class="text-[11px] font-medium text-[#94A3B8] font-mono uppercase tracking-wider">Topics per category</label>
              <input v-model.number="discoveryParams.limit" type="number" min="1" max="20" class="w-full bg-[#0a0e1a] border border-white/[0.08] rounded-[14px] px-4 py-2.5 text-sm text-white focus:outline-none focus:border-[#f5a623]/40 transition-colors" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[11px] font-medium text-[#94A3B8] font-mono uppercase tracking-wider">Freshness (hours)</label>
              <input v-model.number="discoveryParams.fresh_hours" type="number" min="1" max="48" class="w-full bg-[#0a0e1a] border border-white/[0.08] rounded-[14px] px-4 py-2.5 text-sm text-white focus:outline-none focus:border-[#f5a623]/40 transition-colors" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[11px] font-medium text-[#94A3B8] font-mono uppercase tracking-wider">Sources per topic</label>
              <input v-model.number="discoveryParams.sources_per_topic" type="number" min="2" max="6" class="w-full bg-[#0a0e1a] border border-white/[0.08] rounded-[14px] px-4 py-2.5 text-sm text-white focus:outline-none focus:border-[#f5a623]/40 transition-colors" />
            </div>
          </div>

          <button @click="triggerDiscovery" :disabled="discovering" class="mt-6 px-6 py-2.5 bg-[#1a2233] hover:bg-[#2a3245] text-white rounded-[14px] text-sm font-medium transition-all disabled:opacity-50 flex items-center gap-2"
          >
            <Loader2 v-if="discovering" class="w-4 h-4 animate-spin" />
            <Play v-else class="w-4 h-4" />
            {{ discovering ? 'Discovering...' : 'Start Discovery' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Discovery Result -->
    <div v-if="discoveryResult" class="bg-[#111827] rounded-[14px] border border-emerald-500/20 overflow-hidden">
      <div class="px-6 py-4 border-b border-emerald-500/20 bg-emerald-500/10 flex items-center gap-2">
        <CheckCircle class="w-5 h-5 text-emerald-400" />
        <h2 class="text-[15px] font-semibold text-emerald-300">Discovery Complete</h2>
      </div>
      <div class="p-6">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 mb-6">
          <div class="text-center">
            <p class="text-3xl font-bold text-white font-mono">{{ discoveryResult.discovered }}</p>
            <p class="text-xs text-[#94A3B8] mt-1 font-mono">Discovered</p>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold text-emerald-400 font-mono">{{ discoveryResult.queued_for_generation }}</p>
            <p class="text-xs text-[#94A3B8] mt-1 font-mono">Queued</p>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold text-white font-mono">{{ discoveryResult.skipped_existing || 0 }}</p>
            <p class="text-xs text-[#94A3B8] mt-1 font-mono">Existing (skipped)</p>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold text-white font-mono">{{ discoveryResult.duration_seconds || 0 }}s</p>
            <p class="text-xs text-[#94A3B8] mt-1 font-mono">Duration</p>
          </div>
        </div>

        <div v-if="discoveryResult.topics?.length">
          <p class="text-[11px] font-medium text-[#94A3B8] uppercase tracking-wider mb-3 font-mono">New Topics</p>
          <div class="space-y-2">
            <div v-for="t in discoveryResult.topics.slice(0, 8)" :key="t.signature" class="flex items-center justify-between p-3 bg-white/[0.04] rounded-[14px] border border-white/[0.06]">
              <div class="flex items-center gap-3">
                <span class="text-xs px-2 py-0.5 rounded-md bg-[#1a2233] text-[#94A3B8] font-medium font-mono">{{ t.category }}</span>
                <span class="text-sm text-white">{{ t.name }}</span>
              </div>
              <span class="text-xs text-[#64748B] font-mono">{{ t.source_count }} sources</span>
            </div>
            <p v-if="discoveryResult.topics.length > 8" class="text-xs text-[#64748B] text-center font-mono">+{{ discoveryResult.topics.length - 8 }} more...</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Retry Failed -->
    <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-6">
      <div class="flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-amber-400/10 flex items-center justify-center shrink-0"><RotateCcw class="w-5 h-5 text-amber-400" /></div>
        <div class="flex-1">
          <h2 class="text-[15px] font-semibold text-white">Retry Failed Topics</h2>
          <p class="text-[13px] text-[#94A3B8] mt-1">Reset all failed topics and queue them for regeneration.</p>
          <button @click="retryAllFailed" :disabled="retrying" class="mt-6 px-6 py-2.5 border border-amber-400/20 text-amber-400 bg-amber-400/10 hover:bg-amber-400/20 rounded-[14px] text-sm font-medium transition-all disabled:opacity-50 flex items-center gap-2"
          >
            <Loader2 v-if="retrying" class="w-4 h-4 animate-spin" />
            <RotateCcw v-else class="w-4 h-4" />
            {{ retrying ? 'Retrying...' : 'Retry All Failed' }}
          </button>
          <div v-if="retryResult" class="mt-4 p-4 bg-emerald-500/10 rounded-[14px] border border-emerald-500/20">
            <p class="text-sm text-emerald-300 font-medium">✓ Retried {{ retryResult.retried }} topics successfully.</p>
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
