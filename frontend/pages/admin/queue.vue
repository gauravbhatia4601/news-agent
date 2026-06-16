<template>
  <div class="space-y-6">
    <!-- Header + controls -->
    <div class="flex items-center justify-between bg-white rounded-lg border border-slate-200 p-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-slate-900 text-white flex items-center justify-center">
          <Activity class="w-5 h-5" />
        </div>
        <div>
          <h1 class="font-display text-lg font-bold text-slate-900">Live Queue Monitor</h1>
          <p class="text-xs text-slate-500">
            Refreshes every 5s · last update {{ lastUpdate ? formatTime(lastUpdate) : 'never' }}
          </p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="isPaused = !isPaused"
          class="px-3 py-1.5 rounded-md text-xs font-medium border transition-colors"
          :class="isPaused ? 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' : 'border-slate-300 text-slate-600 hover:bg-slate-50'"
        >
          {{ isPaused ? 'Resume' : 'Pause' }}
        </button>
        <button
          @click="load(true)"
          :disabled="refreshing"
          class="px-3 py-1.5 rounded-md text-xs font-medium bg-slate-900 text-white hover:bg-slate-800 disabled:opacity-50 flex items-center gap-1.5"
        >
          <RefreshCw :class="refreshing ? 'animate-spin' : ''" class="w-3.5 h-3.5" />
          Refresh
        </button>
      </div>
    </div>

    <!-- Top status cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Pending Jobs</p>
        <p class="text-3xl font-bold mt-1" :class="queue.pending_jobs > 0 ? 'text-blue-600' : 'text-slate-400'">
          {{ queue.pending_jobs || 0 }}
        </p>
        <p class="text-xs text-slate-400 mt-1">
          {{ queue.pending_jobs_list?.length || 0 }} visible in feed
        </p>
      </div>

      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Failed Jobs</p>
        <p class="text-3xl font-bold mt-1" :class="queue.total_failed_jobs > 0 ? 'text-red-600' : 'text-slate-400'">
          {{ queue.total_failed_jobs || 0 }}
        </p>
        <p class="text-xs text-slate-400 mt-1">Total in failed_jobs table</p>
      </div>

      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Worker Status</p>
        <div class="mt-2 flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full" :class="workerBadgeClass" />
          <span class="font-semibold">{{ workerStatusText }}</span>
        </div>
        <p class="text-xs text-slate-500 mt-1">{{ queue.worker_status?.details || 'Unknown' }}</p>
      </div>

      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Throughput</p>
        <p class="text-3xl font-bold mt-1 text-emerald-600">{{ generation.articles_last_hour || 0 }}</p>
        <p class="text-xs text-slate-400 mt-1">articles in last hour ({{ generation.articles_last_24h || 0 }} in 24h)</p>
      </div>

      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">Failed Topics</p>
        <p class="text-3xl font-bold mt-1" :class="generation.failed_topics > 0 ? 'text-red-600' : 'text-slate-400'">
          {{ generation.failed_topics || 0 }}
        </p>
        <p class="text-xs text-slate-400 mt-1">generation failures, not queue failures</p>
      </div>
    </div>

    <!-- Source API hits + discovery schedule -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Source hits -->
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <h2 class="font-semibold mb-3 flex items-center gap-2">
          <Globe class="w-4 h-4 text-slate-500" /> Source API Hits
        </h2>
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded bg-blue-100 text-blue-700 flex items-center justify-center">
                <Rss class="w-4 h-4" />
              </div>
              <div>
                <p class="text-sm font-medium">Google News RSS</p>
                <p class="text-xs text-slate-500">Primary source</p>
              </div>
            </div>
            <p class="text-xl font-bold">{{ sources.google_rss_hits || 0 }}</p>
          </div>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded bg-amber-100 text-amber-700 flex items-center justify-center">
                <Search class="w-4 h-4" />
              </div>
              <div>
                <p class="text-sm font-medium">Brave Search API</p>
                <p class="text-xs text-slate-500">
                  Fallback, threshold {{ sources.brave_fallback_threshold || 8 }} hits
                </p>
              </div>
            </div>
            <p class="text-xl font-bold">{{ sources.brave_search_hits || 0 }}</p>
          </div>
          <div v-if="sources.brave_search_enabled" class="text-xs bg-slate-50 border border-slate-100 rounded p-2 text-slate-600">
            Brave is enabled as fallback only. Current monthly limit: 1,000 requests.
          </div>
          <div v-else class="text-xs bg-amber-50 border border-amber-100 rounded p-2 text-amber-700">
            Brave fallback is disabled.
          </div>
        </div>
      </div>

      <!-- Discovery schedule -->
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <h2 class="font-semibold mb-3 flex items-center gap-2">
          <CalendarClock class="w-4 h-4 text-slate-500" /> Discovery Schedule
        </h2>
        <div class="space-y-3 text-sm">
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Last run</span>
            <span class="font-medium">{{ discovery.last_run_at ? formatTime(discovery.last_run_at) : 'Never' }}</span>
          </div>
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Next run</span>
            <span class="font-medium">{{ discovery.next_run_at ? formatTime(discovery.next_run_at) : 'Unknown' }}</span>
          </div>
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Frequency</span>
            <span class="font-medium">{{ discovery.frequency || 'hourly' }}</span>
          </div>
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Command</span>
            <span class="font-mono text-xs">{{ discovery.command || 'news:discover --queue' }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500">Next in</span>
            <span class="font-medium">{{ discovery.next_run_in_seconds !== null ? fmtCountdown(discovery.next_run_in_seconds) : 'Unknown' }}</span>
          </div>
        </div>
      </div>

      <!-- Generation config -->
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <h2 class="font-semibold mb-3 flex items-center gap-2">
          <Cpu class="w-4 h-4 text-slate-500" /> Generation Engine
        </h2>
        <div class="space-y-3 text-sm">
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Model</span>
            <span class="font-mono font-medium">{{ generation.model || '—' }}</span>
          </div>
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Provider</span>
            <span class="font-medium">{{ generation.provider || '—' }}</span>
          </div>
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Status</span>
            <span class="font-medium" :class="generation.enabled ? 'text-emerald-600' : 'text-red-600'">
              {{ generation.enabled ? 'Enabled' : 'Disabled' }}
            </span>
          </div>
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-slate-500">Avg gen time</span>
            <span class="font-medium">{{ fmtDuration(generation.avg_generation_seconds) }}</span>
          </div>
          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="bg-slate-50 rounded p-2">
              <p class="text-lg font-bold text-blue-600">{{ generation.pending_topics || 0 }}</p>
              <p class="text-[10px] uppercase text-slate-500">Pending topics</p>
            </div>
            <div class="bg-slate-50 rounded p-2">
              <p class="text-lg font-bold text-emerald-600">{{ generation.generated_topics || 0 }}</p>
              <p class="text-[10px] uppercase text-slate-500">Generated</p>
            </div>
            <div class="bg-slate-50 rounded p-2">
              <p class="text-lg font-bold text-red-600">{{ generation.failed_topics || 0 }}</p>
              <p class="text-[10px] uppercase text-slate-500">Failed</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pending jobs table -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
        <h2 class="font-semibold flex items-center gap-2">
          <List class="w-4 h-4 text-slate-500" /> Pending Jobs ({{ queue.pending_jobs_list?.length || 0 }})
        </h2>
        <span v-if="queue.pending_jobs_list?.length" class="text-xs text-slate-500">Oldest first</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="text-left px-4 py-2 text-xs font-semibold text-slate-500">ID</th>
              <th class="text-left px-4 py-2 text-xs font-semibold text-slate-500">Command / Topic</th>
              <th class="text-left px-4 py-2 text-xs font-semibold text-slate-500">Queue</th>
              <th class="text-left px-4 py-2 text-xs font-semibold text-slate-500">Available Since</th>
              <th class="text-left px-4 py-2 text-xs font-semibold text-slate-500">Attempts</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="!queue.pending_jobs_list?.length">
              <td colspan="5" class="px-4 py-6 text-center text-slate-400">No pending jobs. Queue is idle.</td>
            </tr>
            <tr v-for="job in queue.pending_jobs_list" :key="job.id" class="hover:bg-slate-50">
              <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ shortId(job.id) }}</td>
              <td class="px-4 py-3">
                <span class="font-medium">{{ job.command || 'GenerateArticle' }}</span>
              </td>
              <td class="px-4 py-3">{{ job.queue }}</td>
              <td class="px-4 py-3 text-slate-500">{{ job.available_at ? formatTime(job.available_at) : '—' }}</td>
              <td class="px-4 py-3">{{ job.attempts || 0 }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Failed jobs table -->
    <div class="bg-white rounded-lg border border-red-200 overflow-hidden">
      <div class="px-4 py-3 border-b border-red-100 bg-red-50 flex items-center justify-between">
        <h2 class="font-semibold text-red-700 flex items-center gap-2">
          <AlertTriangle class="w-4 h-4" /> Failed Jobs ({{ queue.recent_failed_jobs?.length || 0 }} recent)
        </h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-red-50/50">
            <tr>
              <th class="text-left px-4 py-2 text-xs font-semibold text-slate-500">ID</th>
              <th class="text-left px-4 py-2 text-xs font-semibold text-slate-500">Command / Topic</th>
              <th class="text-left px-4 py-2 text-xs font-semibold text-slate-500">Queue</th>
              <th class="text-left px-4 py-2 text-xs font-semibold text-slate-500">Failed At</th>
              <th class="text-left px-4 py-2 text-xs font-semibold text-slate-500">Exception</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="!queue.recent_failed_jobs?.length">
              <td colspan="5" class="px-4 py-6 text-center text-slate-400">No failed jobs. 🎉</td>
            </tr>
            <tr v-for="job in queue.recent_failed_jobs" :key="job.id" class="hover:bg-red-50/30 align-top">
              <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ shortId(job.id) }}</td>
              <td class="px-4 py-3">
                <span class="font-medium">{{ job.command || 'GenerateArticle' }}</span>
              </td>
              <td class="px-4 py-3">{{ job.queue }}</td>
              <td class="px-4 py-3 text-slate-500">{{ job.failed_at ? formatTime(job.failed_at) : '—' }}</td>
              <td class="px-4 py-3">
                <div class="max-w-xl">
                  <pre class="text-xs text-red-700 bg-red-50 rounded p-2 overflow-x-auto whitespace-pre-wrap">{{ job.exception_preview || job.exception }}</pre>
                  <button
                    v-if="job.exception && (job.exception_preview || '').length < (job.exception || '').length"
                    @click="expandedFailed = expandedFailed === job.id ? null : job.id"
                    class="mt-1 text-xs text-slate-500 hover:text-slate-800 underline"
                  >
                    {{ expandedFailed === job.id ? 'Collapse' : 'Expand full trace' }}
                  </button>
                  <pre v-if="expandedFailed === job.id" class="text-xs text-slate-700 bg-slate-50 rounded p-2 overflow-x-auto whitespace-pre-wrap mt-2">{{ job.exception }}</pre>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Activity, RefreshCw, Globe, Rss, Search, CalendarClock, Cpu, List, AlertTriangle } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()

const queue = ref<any>({ pending_jobs: 0, total_failed_jobs: 0, pending_jobs_list: [], recent_failed_jobs: [], worker_status: {} })
const sources = ref<any>({ google_rss_hits: 0, brave_search_hits: 0, brave_search_enabled: true, brave_fallback_threshold: 8 })
const discovery = ref<any>({ last_run_at: null, next_run_at: null, next_run_in_seconds: null })
const generation = ref<any>({ model: '', provider: '', enabled: true, articles_last_hour: 0, articles_last_24h: 0, avg_generation_seconds: 0 })
const lastUpdate = ref<string | null>(null)
const refreshing = ref(false)
const isPaused = ref(false)
const expandedFailed = ref<string | number | null>(null)

let interval: ReturnType<typeof setInterval> | null = null

async function load(force = false) {
  if (isPaused.value && !force) return
  refreshing.value = true
  try {
    const res = await api.getQueueStatus()
    const d = res.data
    queue.value = d.queue || queue.value
    sources.value = d.sources || sources.value
    discovery.value = d.discovery || discovery.value
    generation.value = d.generation || generation.value
    lastUpdate.value = d.timestamp || new Date().toISOString()
  } catch (e: any) {
    console.error('Queue monitor load failed', e)
  } finally {
    refreshing.value = false
  }
}

const workerStatusText = computed(() => {
  const ws = queue.value.worker_status || {}
  if (ws.stalled) return 'Stalled'
  if (ws.queue_work_running) return ws.pending_jobs > 0 ? 'Running & busy' : 'Running (idle)'
  return 'Not detected'
})

const workerBadgeClass = computed(() => {
  const ws = queue.value.worker_status || {}
  if (ws.stalled) return 'bg-amber-500 animate-pulse'
  if (ws.queue_work_running) return 'bg-emerald-500'
  return 'bg-red-500'
})

function formatTime(iso: string): string {
  try {
    return new Date(iso).toLocaleString(undefined, {
      month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit'
    })
  } catch {
    return String(iso)
  }
}

function fmtDuration(seconds: number): string {
  if (!seconds) return '0s'
  if (seconds < 60) return `${Math.round(seconds)}s`
  const m = Math.floor(seconds / 60)
  const s = Math.round(seconds % 60)
  return `${m}m ${s}s`
}

function fmtCountdown(totalSeconds: number): string {
  if (totalSeconds <= 0) return 'Due now'
  const h = Math.floor(totalSeconds / 3600)
  const m = Math.floor((totalSeconds % 3600) / 60)
  const s = totalSeconds % 60
  return `${h}h ${m}m ${s}s`
}

function shortId(id: string | number): string {
  const s = String(id)
  return s.length > 12 ? s.slice(0, 12) + '…' : s
}

onMounted(() => {
  load()
  interval = setInterval(() => load(), 5000)
})

onUnmounted(() => {
  if (interval) clearInterval(interval)
})
</script>
