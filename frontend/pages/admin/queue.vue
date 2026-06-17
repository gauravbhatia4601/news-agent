<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-[#1a2233] text-white flex items-center justify-center"><Activity class="w-5 h-5" /></div>
        <div>
          <h1 class="text-lg font-bold text-gray-900">Queue Monitor</h1>
          <p class="text-xs text-gray-500">Refreshes every 5s · last update {{ lastUpdate ? formatTime(lastUpdate) : 'never' }}</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button @click="isPaused = !isPaused" class="px-3.5 py-2 rounded-xl text-xs font-medium border transition-all" :class="isPaused ? 'border-emerald-200 text-emerald-700 bg-emerald-50 hover:bg-emerald-100' : 'border-gray-200 text-gray-600 bg-white hover:bg-gray-50'"><Pause v-if="!isPaused" class="w-3.5 h-3.5 mr-1.5 inline" /><Play v-else class="w-3.5 h-3.5 mr-1.5 inline" />{{ isPaused ? 'Resume' : 'Pause' }}</button>
        <button @click="load(true)" :disabled="refreshing" class="px-3.5 py-2 rounded-xl text-xs font-medium bg-[#1a2233] text-white hover:bg-[#2a3245] disabled:opacity-50 flex items-center gap-1.5 transition-all"><RefreshCw :class="refreshing ? 'animate-spin' : ''" class="w-3.5 h-3.5" />Refresh</button>
      </div>
    </div>

    <!-- Status Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl border border-gray-200/60 p-5 hover:shadow-lg hover:shadow-gray-200/30 transition-all">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[10px] uppercase tracking-[0.12em] text-gray-500 font-semibold">Pending Jobs</span>
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center"><Clock class="w-4 h-4 text-blue-500" /></div>
        </div>
        <p class="text-3xl font-bold text-gray-900" :class="queue.pending_jobs > 0 ? 'text-blue-600' : ''">{{ queue.pending_jobs || 0 }}</p>
        <p class="text-xs text-gray-400 mt-1">{{ queue.pending_jobs_list?.length || 0 }} visible in feed</p>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200/60 p-5 hover:shadow-lg hover:shadow-gray-200/30 transition-all">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[10px] uppercase tracking-[0.12em] text-gray-500 font-semibold">Failed Jobs</span>
          <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center"><XCircle class="w-4 h-4 text-red-500" /></div>
        </div>
        <p class="text-3xl font-bold" :class="queue.total_failed_jobs > 0 ? 'text-red-600' : 'text-gray-300'">{{ queue.total_failed_jobs || 0 }}</p>
        <p class="text-xs text-gray-400 mt-1">Total in failed_jobs table</p>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200/60 p-5 hover:shadow-lg hover:shadow-gray-200/30 transition-all">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[10px] uppercase tracking-[0.12em] text-gray-500 font-semibold">Worker Status</span>
          <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center"><HardDrive class="w-4 h-4 text-gray-500" /></div>
        </div>
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full" :class="workerBadgeClass" />
          <span class="font-semibold text-sm">{{ workerStatusText }}</span>
        </div>
        <p class="text-xs text-gray-400 mt-1 truncate">{{ queue.worker_status?.details || 'Unknown' }}</p>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200/60 p-5 hover:shadow-lg hover:shadow-gray-200/30 transition-all">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[10px] uppercase tracking-[0.12em] text-gray-500 font-semibold">Throughput</span>
          <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center"><TrendingUp class="w-4 h-4 text-emerald-600" /></div>
        </div>
        <p class="text-3xl font-bold text-emerald-600">{{ generation.articles_last_hour || 0 }}</p>
        <p class="text-xs text-gray-400 mt-1">articles in last hour ({{ generation.articles_last_24h || 0 }} in 24h)</p>
      </div>
    </div>

    <!-- Three Column Info -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Source API Hits -->
      <div class="bg-white rounded-2xl border border-gray-200/60 p-6">
        <h2 class="text-sm font-semibold mb-5 flex items-center gap-2 text-gray-900">
          <Globe class="w-4 h-4 text-gray-400" /> Source API Hits
        </h2>
        <div class="space-y-5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center"><Rss class="w-5 h-5" /></div>
              <div>
                <p class="text-sm font-medium text-gray-900">Google News RSS</p>
                <p class="text-xs text-gray-400">Primary source</p>
              </div>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ sources.google_rss_hits || 0 }}</p>
          </div>

          <div class="h-px bg-gray-100" />

          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center"><Search class="w-5 h-5" /></div>
              <div>
                <p class="text-sm font-medium text-gray-900">Brave Search API</p>
                <p class="text-xs text-gray-400">Fallback (threshold {{ sources.brave_fallback_threshold || 8 }})</p>
              </div>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ sources.brave_search_hits || 0 }}</p>
          </div>

          <div :class="sources.brave_search_enabled ? 'bg-gray-50 border border-gray-100 rounded-xl p-3 text-xs text-gray-600' : 'bg-amber-50 border border-amber-100 rounded-xl p-3 text-xs text-amber-700'">
            {{ sources.brave_search_enabled ? 'Brave is enabled as fallback. Monthly limit: 1,000 requests.' : 'Brave fallback is disabled.' }}
          </div>
        </div>
      </div>

      <!-- Discovery Schedule -->
      <div class="bg-white rounded-2xl border border-gray-200/60 p-6">
        <h2 class="text-sm font-semibold mb-5 flex items-center gap-2 text-gray-900">
          <CalendarClock class="w-4 h-4 text-gray-400" /> Discovery Schedule
        </h2>
        <div class="space-y-3">
          <div v-for="item in scheduleItems" :key="item.label" class="flex items-center justify-between py-3 border-b border-gray-50 last:border-0">
            <span class="text-sm text-gray-500">{{ item.label }}</span>
            <span class="text-sm font-medium text-gray-900">{{ item.value }}</span>
          </div>
        </div>
      </div>

      <!-- Generation Config -->
      <div class="bg-white rounded-2xl border border-gray-200/60 p-6">
        <h2 class="text-sm font-semibold mb-5 flex items-center gap-2 text-gray-900">
          <Cpu class="w-4 h-4 text-gray-400" /> Generation Engine
        </h2>
        <div class="space-y-3 mb-5">
          <div v-for="item in genItems" :key="item.label" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
            <span class="text-sm text-gray-500">{{ item.label }}</span>
            <span class="text-sm font-medium text-gray-900">{{ item.value }}</span>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div class="bg-gray-50 rounded-xl p-3 text-center">
            <p class="text-xl font-bold text-blue-600">{{ generation.pending_topics || 0 }}</p>
            <p class="text-[10px] uppercase tracking-wider text-gray-500 mt-1">Pending</p>
          </div>
          <div class="bg-gray-50 rounded-xl p-3 text-center">
            <p class="text-xl font-bold text-emerald-600">{{ generation.generated_topics || 0 }}</p>
            <p class="text-[10px] uppercase tracking-wider text-gray-500 mt-1">Generated</p>
          </div>
          <div class="bg-gray-50 rounded-xl p-3 text-center">
            <p class="text-xl font-bold text-red-500">{{ generation.failed_topics || 0 }}</p>
            <p class="text-[10px] uppercase tracking-wider text-gray-500 mt-1">Failed</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Pending Jobs Table -->
    <div class="bg-white rounded-2xl border border-gray-200/60 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900"><List class="w-4 h-4 text-gray-400 inline mr-2"></List>Pending Jobs ({{ queue.pending_jobs_list?.length || 0 }})</h2>
        <span v-if="queue.pending_jobs_list?.length" class="text-xs text-gray-400">Oldest first</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50/80">
              <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">ID</th>
              <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Command / Topic</th>
              <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Queue</th>
              <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Available Since</th>
              <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Attempts</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="!queue.pending_jobs_list?.length"><td colspan="5" class="px-6 py-10 text-center text-gray-400">
              <CheckCircle class="w-8 h-8 mx-auto mb-2 text-gray-200" />
              No pending jobs. Queue is idle.
            </td></tr>
            <tr v-for="job in queue.pending_jobs_list" :key="job.id" class="hover:bg-gray-50/50 transition-colors">
              <td class="px-6 py-3 font-mono text-xs text-gray-400">{{ shortId(job.id) }}</td>
              <td class="px-6 py-3 font-medium text-gray-900">{{ job.command || 'GenerateArticle' }}</td>
              <td class="px-6 py-3">{{ job.queue }}</td>
              <td class="px-6 py-3 text-gray-400">{{ job.available_at ? formatTime(job.available_at) : '—' }}</td>
              <td class="px-6 py-3">{{ job.attempts || 0 }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Job History -->
    <div class="bg-white rounded-2xl border border-gray-200/60 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-sm font-semibold text-gray-900"><List class="w-4 h-4 text-gray-400 inline mr-2"></List>Job History ({{ history.total || 0 }})</h2>
        <div class="flex items-center gap-2">
          <label class="text-xs text-gray-500">Status:</label>
          <select v-model="historyFilter.status" @change="loadHistory(1)" class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-[#1a2233] transition-colors">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="processed">Processed</option>
            <option value="failed">Failed</option>
            <option value="released">Released</option>
          </select>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50/80">
              <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Command / Topic</th>
              <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Queue</th>
              <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Attempts</th>
              <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Started</th>
              <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Duration</th>
              <th class="text-left px-6 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Exception</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="!history.jobs?.length"><td colspan="7" class="px-6 py-10 text-center text-gray-400">
              <List class="w-8 h-8 mx-auto mb-2 text-gray-200" />
              No job history yet.
            </td></tr>
            <tr v-for="job in history.jobs" :key="job.id" class="hover:bg-gray-50/50 transition-colors align-top">
              <td class="px-6 py-3"><span class="text-[11px] font-medium px-2.5 py-1 rounded-full" :class="historyStatusClass(job.status)">{{ job.status }}</span></td>
              <td class="px-6 py-3"><div class="font-medium text-gray-900">{{ job.job_class || 'GenerateArticle' }}</div><div v-if="job.job_signature" class="text-xs text-gray-400 font-mono mt-0.5">{{ shortId(job.job_signature) }}</div></td>
              <td class="px-6 py-3">{{ job.queue }}</td>
              <td class="px-6 py-3">{{ job.attempts || 0 }}</td>
              <td class="px-6 py-3 text-gray-400">{{ job.started_at ? formatTime(job.started_at) : formatTime(job.created_at) }}</td>
              <td class="px-6 py-3">{{ job.duration_seconds ? fmtDuration(job.duration_seconds) : '—' }}</td>
              <td class="px-6 py-3 max-w-xl">
                <div v-if="job.exception">
                  <pre class="text-xs text-red-700 bg-red-50/50 rounded-lg p-3 overflow-x-auto whitespace-pre-wrap border border-red-100">{{ strLimit(job.exception, 200) }}</pre>
                  <button v-if="job.exception.length > 200" @click="expandedHistory = expandedHistory === job.id ? null : job.id" class="mt-2 text-[11px] text-gray-400 hover:text-gray-700">{{ expandedHistory === job.id ? 'Collapse' : 'Expand full trace' }}</button>
                  <pre v-if="expandedHistory === job.id" class="text-xs text-gray-700 bg-gray-50 rounded-lg p-3 overflow-x-auto whitespace-pre-wrap mt-2 border border-gray-100">{{ job.exception }}</pre>
                </div>
                <span v-else class="text-gray-300">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="history.last_page > 1" class="px-6 py-3 border-t border-gray-100 flex items-center justify-between">
        <span class="text-xs text-gray-500">Page {{ history.current_page }} of {{ history.last_page }}</span>
        <div class="flex gap-1.5">
          <button @click="loadHistory(history.current_page - 1)" :disabled="history.current_page <= 1" class="px-3 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 transition-colors">Previous</button>
          <button @click="loadHistory(history.current_page + 1)" :disabled="history.current_page >= history.last_page" class="px-3 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 transition-colors">Next</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Activity, RefreshCw, Globe, Rss, Search, CalendarClock, Cpu, List, Clock, XCircle, HardDrive, TrendingUp, Pause, Play, CheckCircle } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const queue = ref<any>({ pending_jobs: 0, total_failed_jobs: 0, pending_jobs_list: [], worker_status: {} })
const sources = ref<any>({ google_rss_hits: 0, brave_search_hits: 0, brave_search_enabled: true, brave_fallback_threshold: 8 })
const discovery = ref<any>({ last_run_at: null, next_run_at: null, next_run_in_seconds: null })
const generation = ref<any>({ model: '', provider: '', enabled: true, articles_last_hour: 0, articles_last_24h: 0, avg_generation_seconds: 0, pending_topics: 0, generated_topics: 0, failed_topics: 0 })
const history = ref<any>({ jobs: [], total: 0, current_page: 1, last_page: 1, per_page: 50 })
const historyFilter = reactive({ status: '' })
const lastUpdate = ref<string | null>(null)
const refreshing = ref(false)
const isPaused = ref(false)
const expandedHistory = ref<number | null>(null)

let interval: ReturnType<typeof setInterval> | null = null

async function load(force = false) {
  if (isPaused.value && !force) return
  refreshing.value = true
  try {
    const [statusRes, historyRes] = await Promise.all([
      api.getQueueStatus(),
      api.getQueueHistory({ status: historyFilter.status, page: history.value.current_page, per_page: history.value.per_page })
    ])
    const d = statusRes.data
    queue.value = d.queue || queue.value
    sources.value = d.sources || sources.value
    discovery.value = d.discovery || discovery.value
    generation.value = d.generation || generation.value
    history.value = historyRes.data || history.value
    lastUpdate.value = d.timestamp || new Date().toISOString()
  } catch (e: any) {
    console.error('Queue monitor load failed', e)
  } finally {
    refreshing.value = false
  }
}

async function loadHistory(page = 1) {
  try {
    const res = await api.getQueueHistory({ status: historyFilter.status, page, per_page: history.value.per_page })
    history.value = res.data || history.value
    expandedHistory.value = null
  } catch (e: any) {
    console.error('Queue history load failed', e)
  }
}

function strLimit(value: string | null, limit: number, end = '…'): string {
  if (!value) return ''
  if (value.length <= limit) return value
  return value.slice(0, limit) + end
}

function historyStatusClass(status: string): string {
  switch (status) {
    case 'processed': return 'bg-emerald-50 text-emerald-700 border border-emerald-100'
    case 'processing': return 'bg-blue-50 text-blue-700 border border-blue-100'
    case 'pending': return 'bg-gray-50 text-gray-600 border border-gray-100'
    case 'failed': return 'bg-red-50 text-red-700 border border-red-100'
    case 'released': return 'bg-amber-50 text-amber-700 border border-amber-100'
    default: return 'bg-gray-50 text-gray-600 border border-gray-100'
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

const scheduleItems = computed(() => {
  const d = discovery.value
  return [
    { label: 'Last run', value: d.last_run_at ? formatTime(d.last_run_at) : 'Never' },
    { label: 'Next run', value: d.next_run_at ? formatTime(d.next_run_at) : 'Unknown' },
    { label: 'Frequency', value: d.frequency || 'hourly' },
    { label: 'Command', value: d.command || 'news:discover --queue' },
    { label: 'Next in', value: d.next_run_in_seconds !== null ? fmtCountdown(d.next_run_in_seconds) : 'Unknown' },
  ]
})

const genItems = computed(() => {
  const g = generation.value
  return [
    { label: 'Model', value: g.model || '—' },
    { label: 'Provider', value: g.provider || '—' },
    { label: 'Status', value: g.enabled ? 'Enabled' : 'Disabled' },
    { label: 'Avg gen time', value: fmtDuration(g.avg_generation_seconds) },
  ]
})

function formatTime(iso: string): string {
  try { return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }) }
  catch { return String(iso) }
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
  loadHistory(1)
  interval = setInterval(() => { load(); loadHistory(history.value.current_page) }, 5000)
})
onUnmounted(() => { if (interval) clearInterval(interval) })
</script>