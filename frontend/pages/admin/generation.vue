<template>
  <div class="space-y-6">
    <!-- Quick actions -->
    <div class="bg-white rounded-lg border border-slate-200 p-4">
      <h2 class="font-semibold mb-3">Quick Actions</h2>
      <div class="flex flex-wrap gap-2">
        <button @click="regenerateSitemap" :disabled="regenerating"
          class="px-4 py-2 bg-slate-900 text-white rounded-md text-sm hover:bg-slate-800 disabled:opacity-50 flex items-center gap-2"
        >
          <Loader2 v-if="regenerating" class="w-3.5 h-3.5 animate-spin" />
          <SitemapIcon v-else class="w-3.5 h-3.5" />
          {{ regenerating ? 'Generating...' : 'Regenerate Sitemap' }}
        </button>
        <button @click="retryFailed" :disabled="retrying"
          class="px-4 py-2 border border-amber-300 text-amber-700 rounded-md text-sm hover:bg-amber-50 disabled:opacity-50 flex items-center gap-2"
        >
          <RefreshCw v-if="retrying" class="w-3.5 h-3.5 animate-spin" />
          <RotateCcw v-else class="w-3.5 h-3.5" />
          {{ retrying ? 'Retrying...' : 'Retry Failed Topics' }}
        </button>
      </div>
      <div v-if="actionMessage" class="mt-3 text-sm" :class="actionMessage.type === 'success' ? 'text-emerald-600' : 'text-red-600'">
        {{ actionMessage.text }}
      </div>
    </div>

    <!-- Stats grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Published Articles</p>
        <p class="text-2xl font-bold mt-1">{{ stats.articles?.published || 0 }}</p>
        <p class="text-xs text-emerald-600 mt-1">+{{ stats.articles?.last_24h || 0 }} in 24h</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Pending Topics</p>
        <p class="text-2xl font-bold mt-1 text-blue-600">{{ stats.topics?.pending || 0 }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ stats.topics?.discovered_24h || 0 }} discovered today</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Failed</p>
        <p class="text-2xl font-bold mt-1 text-red-600">{{ stats.topics?.failed || 0 }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ stats.last_24h?.failed || 0 }} in 24h</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Avg Word Count</p>
        <p class="text-2xl font-bold mt-1">{{ stats.articles?.avg_word_count || 0 }}</p>
        <p class="text-xs text-slate-400 mt-1">Target: 700+</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Avg Gen Time</p>
        <p class="text-2xl font-bold mt-1">{{ fmtDuration(stats.articles?.avg_generation_seconds) }}</p>
        <p class="text-xs text-slate-400 mt-1">Last 24h</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Throughput</p>
        <p class="text-2xl font-bold mt-1">{{ stats.articles?.throughput_per_hour || 0 }}</p>
        <p class="text-xs text-slate-400 mt-1">Articles/hour</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Queue Depth</p>
        <p class="text-2xl font-bold mt-1">{{ stats.queue || 0 }}</p>
        <p class="text-xs mt-1">
          <span :class="stats.queue > 0 ? 'text-emerald-600' : 'text-slate-400'">{{ stats.queue > 0 ? 'Processing' : 'Idle' }}</span>
        </p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Success Rate (24h)</p>
        <p class="text-2xl font-bold mt-1" :class="parseFloat(stats.last_24h?.success_rate || '0') > 80 ? 'text-emerald-600' : 'text-amber-600'">{{ stats.last_24h?.success_rate || 0 }}%</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Quality Passed</p>
        <p class="text-2xl font-bold mt-1 text-emerald-600">{{ stats.quality?.passed || 0 }}</p>
        <p class="text-xs text-red-600 mt-1">{{ stats.quality?.failed || 0 }} failed</p>
      </div>
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Images</p>
        <p class="text-2xl font-bold mt-1">{{ stats.images?.total_with_images || 0 }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ stats.images?.ai_generated || 0 }} AI · {{ stats.images?.from_sources || 0 }} Source</p>
      </div>
    </div>

    <!-- Model usage -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <h2 class="font-semibold mb-3">Model Usage</h2>
        <div class="space-y-2">
          <div v-for="model in stats.models" :key="model.model" class="flex items-center justify-between text-sm">
            <span class="text-slate-600">{{ model.model || 'Unknown' }}</span>
            <div class="flex items-center gap-2">
              <div class="w-32 h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-blue-500 rounded-full" :style="{ width: modelPct(model.count) + '%' }" />
              </div>
              <span class="text-slate-500 text-xs w-10 text-right">{{ model.count }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <h2 class="font-semibold mb-3">Top Categories (30 days)</h2>
        <div class="space-y-2">
          <div v-for="cat in stats.top_categories" :key="cat.slug" class="flex items-center justify-between text-sm">
            <span class="text-slate-600">{{ cat.name }}</span>
            <div class="flex items-center gap-2">
              <div class="w-32 h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-emerald-500 rounded-full" :style="{ width: catPct(cat.count) + '%' }" />
              </div>
              <span class="text-slate-500 text-xs w-10 text-right">{{ cat.count }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Queue status -->
    <div class="bg-white rounded-lg border border-slate-200 p-4">
      <h2 class="font-semibold mb-3">Queue Status</h2>
      <div class="flex items-center gap-6 text-sm">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full" :class="queue.pending_jobs > 0 ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300'" />
          <span class="text-slate-600">Pending: {{ queue.pending_jobs }}</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-red-500" />
          <span class="text-slate-600">Failed: {{ queue.total_failed_jobs }}</span>
        </div>
      </div>
    </div>

    <!-- Failed jobs -->
    <div v-if="queue.recent_failed?.length" class="bg-white rounded-lg border border-red-200">
      <div class="px-4 py-3 border-b border-red-100 bg-red-50">
        <h2 class="font-semibold text-red-700">Recent Failed Jobs</h2>
      </div>
      <div class="divide-y divide-slate-100">
        <div v-for="f in queue.recent_failed" :key="f.id" class="px-4 py-3">
          <div class="flex items-center justify-between mb-1">
            <span class="text-xs text-slate-500">Job #{{ f.id }} · {{ f.queue }}</span>
            <span class="text-xs text-slate-500">{{ f.failed_at }}</span>
          </div>
          <p class="text-xs text-red-600 line-clamp-2">{{ f.exception }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Loader2, RefreshCw, RotateCcw } from 'lucide-vue-next'

// Stub icons for sitemap
const SitemapIcon = {
  render: () => h('svg', { class: 'w-3.5 h-3.5', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2' }, [
    h('path', { d: 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z' }),
    h('polyline', { points: '9 22 9 12 15 12 15 22' }),
  ]),
}

import { h } from 'vue'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const stats = ref<any>({})
const queue = ref<any>({})
const regenerating = ref(false)
const retrying = ref(false)
const actionMessage = ref<{ type: string; text: string } | null>(null)

async function load() {
  try {
    const [s, q] = await Promise.all([api.getGenerationStats(), api.getQueueStatus()])
    stats.value = s.data
    queue.value = q.data
  } catch {}
}

async function regenerateSitemap() {
  regenerating.value = true
  actionMessage.value = null
  try {
    const res = await api.regenerateSitemap()
    actionMessage.value = { type: 'success', text: 'Sitemaps regenerated: ' + res.data.files.join(', ') }
  } catch (e: any) {
    actionMessage.value = { type: 'error', text: e?.data?.message || 'Failed to regenerate sitemap' }
  }
  regenerating.value = false
}

async function retryFailed() {
  retrying.value = true
  actionMessage.value = null
  try {
    const res = await api.retryFailed()
    actionMessage.value = { type: 'success', text: `Retried ${res.data.retried} failed topics` }
    await load()
  } catch (e: any) {
    actionMessage.value = { type: 'error', text: e?.data?.message || 'Failed to retry' }
  }
  retrying.value = false
}

function modelPct(count: number) {
  const total = stats.value.models?.reduce((sum: number, m: any) => sum + m.count, 0) || 1
  return total > 0 ? (count / total) * 100 : 0
}

function catPct(count: number) {
  const total = stats.value.top_categories?.reduce((sum: number, c: any) => sum + c.count, 0) || 1
  return total > 0 ? (count / total) * 100 : 0
}

function fmtDuration(seconds: number) {
  if (!seconds) return '0s'
  if (seconds < 60) return `${seconds}s`
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}m ${s}s`
}

onMounted(load)
</script>
