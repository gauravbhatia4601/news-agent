<template>
  <div class="space-y-6">
    <!-- Quick Actions -->
    <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-6">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-[#1a2233]/40 flex items-center justify-center"><Zap class="w-5 h-5 text-[#f5a623]" /></div>
          <div>
            <h2 class="text-[15px] font-semibold text-white">Quick Actions</h2>
            <p class="text-xs text-[#64748B] font-mono">Manual operations and maintenance</p>
          </div>
        </div>
      </div>
      <div class="flex flex-wrap gap-3">
        <button @click="regenerateSitemap" :disabled="regenerating" class="px-4 py-2.5 bg-[#1a2233] hover:bg-[#2a3245] text-white rounded-[14px] text-sm font-medium transition-all disabled:opacity-50 flex items-center gap-2"
        >
          <Loader2 v-if="regenerating" class="w-4 h-4 animate-spin" />
          <SitemapIcon v-else class="w-4 h-4" />
          {{ regenerating ? 'Generating...' : 'Regenerate Sitemap' }}
        </button>
        <button @click="retryFailed" :disabled="retrying" class="px-4 py-2.5 border border-amber-400/20 text-amber-400 bg-amber-400/10 hover:bg-amber-400/20 rounded-[14px] text-sm font-medium transition-all disabled:opacity-50 flex items-center gap-2"
        >
          <Loader2 v-if="retrying" class="w-4 h-4 animate-spin" />
          <RotateCcw v-else class="w-4 h-4" />
          {{ retrying ? 'Retrying...' : 'Retry Failed Topics' }}
        </button>
      </div>
      <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0">
        <div v-if="actionMessage" class="mt-4 p-4 rounded-[14px] text-sm flex items-start gap-2" :class="actionMessage.type === 'success' ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/20' : 'bg-red-500/10 text-red-300 border border-red-500/20'"
        >
          <CheckCircle v-if="actionMessage.type === 'success'" class="w-4 h-4 shrink-0 mt-0.5" />
          <AlertCircle v-else class="w-4 h-4 shrink-0 mt-0.5" />
          {{ actionMessage.text }}
        </div>
      </Transition>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
      <div v-for="card in statCards" :key="card.key" class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-5 hover:shadow-lg hover:shadow-black/20 transition-all duration-300">
        <div class="flex items-center justify-between mb-4">
          <div :class="`w-9 h-9 rounded-lg ${card.iconBg} ${card.iconColor} flex items-center justify-center`">
            <component :is="card.icon" class="w-4 h-4" stroke-width="1.75" />
          </div>
          <div v-if="card.trend != null" class="flex items-center gap-1 text-xs font-medium" :class="card.trend >= 0 ? 'text-emerald-400' : 'text-red-400'">
            <TrendingUp v-if="card.trend >= 0" class="w-3 h-3" />
            <TrendingDown v-else class="w-3 h-3" />
            <span class="font-mono">{{ Math.abs(card.trend).toFixed(0) }}%</span>
          </div>
        </div>
        <p class="text-2xl font-bold text-white tracking-tight font-mono">{{ card.displayValue }}</p>
        <p class="text-xs text-[#94A3B8] mt-1">{{ card.label }}</p>
        <p v-if="card.sub" class="text-[11px] text-[#64748B] mt-1 font-mono">{{ card.sub }}</p>
      </div>
    </div>

    <!-- Two Column Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Model Usage -->
      <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-[15px] font-semibold text-white">Model Usage</h2>
          <span class="text-xs text-[#64748B] font-mono">Last 30 days</span>
        </div>
        <div class="space-y-4">
          <div v-for="model in stats.models" :key="model.model">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm text-[#94A3B8]">{{ model.model || 'Unknown' }}</span>
              <span class="text-xs font-medium text-white font-mono">{{ model.count }}</span>
            </div>
            <div class="h-2 bg-white/[0.06] rounded-full overflow-hidden">
              <div class="h-full rounded-full bg-[#f5a623] transition-all duration-700" :style="`width: ${modelPct(model.count)}%`"></div>
            </div>
          </div>
          <div v-if="!(stats.models?.length)" class="text-center py-8">
            <Cpu class="w-8 h-8 text-[#475569] mx-auto mb-2" />
            <p class="text-sm text-[#94A3B8]">No model data yet</p>
          </div>
        </div>
      </div>

      <!-- Top Categories -->
      <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-[15px] font-semibold text-white">Top Categories</h2>
          <span class="text-xs text-[#64748B] font-mono">Last 30 days</span>
        </div>
        <div class="space-y-4">
          <div v-for="cat in stats.top_categories" :key="cat.slug">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm text-[#94A3B8]">{{ cat.name }}</span>
              <span class="text-xs font-medium text-white font-mono">{{ cat.count }}</span>
            </div>
            <div class="h-2 bg-white/[0.06] rounded-full overflow-hidden">
              <div class="h-full rounded-full bg-[#f5a623] transition-all duration-700" :style="`width: ${catPct(cat.count)}%`"></div>
            </div>
          </div>
          <div v-if="!(stats.top_categories?.length)" class="text-center py-8">
            <BarChart3 class="w-8 h-8 text-[#475569] mx-auto mb-2" />
            <p class="text-sm text-[#94A3B8]">No category data yet</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Queue Health -->
    <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-[15px] font-semibold text-white">Queue Status</h2>
        <NuxtLink to="/admin/queue" class="text-xs text-[#f5a623] font-medium hover:underline underline-offset-4 font-mono">View Details →</NuxtLink>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="flex items-center gap-3 p-4 rounded-[14px] bg-white/[0.04] border border-white/[0.06]">
          <div class="w-2.5 h-2.5 rounded-full" :class="queue.pending_jobs > 0 ? 'bg-emerald-500 animate-pulse' : 'bg-[#475569]'" />
          <div>
            <p class="text-xs text-[#94A3B8] font-mono">Pending Jobs</p>
            <p class="text-lg font-bold text-white font-mono">{{ queue.pending_jobs || 0 }}</p>
          </div>
        </div>
        <div class="flex items-center gap-3 p-4 rounded-[14px] bg-white/[0.04] border border-white/[0.06]">
          <div class="w-2.5 h-2.5 rounded-full bg-red-500" />
          <div>
            <p class="text-xs text-[#94A3B8] font-mono">Failed Jobs</p>
            <p class="text-lg font-bold text-white font-mono">{{ queue.total_failed_jobs || 0 }}</p>
          </div>
        </div>
        <div class="flex items-center gap-3 p-4 rounded-[14px] bg-white/[0.04] border border-white/[0.06]">
          <div class="w-2.5 h-2.5 rounded-full" :class="parseFloat(stats.last_24h?.success_rate || '0') > 80 ? 'bg-emerald-500' : 'bg-amber-400'" />
          <div>
            <p class="text-xs text-[#94A3B8] font-mono">24h Success Rate</p>
            <p class="text-lg font-bold text-white font-mono">{{ stats.last_24h?.success_rate || 0 }}%</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Failures -->
    <div v-if="queue.recent_failed?.length" class="bg-[#111827] rounded-[14px] border border-red-500/20 overflow-hidden">
      <div class="px-6 py-4 border-b border-red-500/20 bg-red-500/10 flex items-center gap-2">
        <AlertTriangle class="w-4 h-4 text-red-400" />
        <h2 class="text-[15px] font-semibold text-red-300">Recent Failed Jobs</h2>
      </div>
      <div class="divide-y divide-white/[0.04]">
        <div v-for="f in queue.recent_failed" :key="f.id" class="px-6 py-4 flex items-start gap-4">
          <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0"><XCircle class="w-4 h-4 text-red-400" /></div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
              <span class="text-xs text-[#94A3B8] font-mono">Job #{{ f.id }} · {{ f.queue }}</span>
              <span class="text-xs text-[#64748B] font-mono">{{ f.failed_at }}</span>
            </div>
            <p class="text-xs text-red-400 mt-1 line-clamp-2 font-mono">{{ f.exception }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Loader2, RefreshCw, RotateCcw, TrendingUp, TrendingDown, Cpu, BarChart3, Zap, CheckCircle, AlertCircle, AlertTriangle, XCircle } from 'lucide-vue-next'
import { h } from 'vue'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const SitemapIcon = {
  render: () => h('svg', { class: 'w-4 h-4', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2' }, [
    h('path', { d: 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z' }),
    h('polyline', { points: '9 22 9 12 15 12 15 22' }),
  ]),
}

const api = useAdminApi()
const stats = ref<any>({})
const queue = ref<any>({})
const regenerating = ref(false)
const retrying = ref(false)
const actionMessage = ref<{ type: string; text: string } | null>(null)

const statCards = computed(() => {
  const s = stats.value
  const total = s?.articles?.published || 0
  return [
    { key:'published', label:'Published Articles', displayValue:s?.articles?.published || '—', icon:FileText, iconBg:'bg-[#1a2233]/40', iconColor:'text-[#f5a623]', trend:12, sub:`+${s?.articles?.last_24h || 0} in 24h` },
    { key:'pending', label:'Pending Topics', displayValue:s?.topics?.pending || '—', icon:FolderOpen, iconBg:'bg-blue-500/10', iconColor:'text-blue-400', trend:5, sub:`${s?.topics?.discovered_24h || 0} discovered today` },
    { key:'failed', label:'Failed', displayValue:s?.topics?.failed || '—', icon:XCircle, iconBg:'bg-red-500/10', iconColor:'text-red-400', trend:-2, sub:`${s?.last_24h?.failed || 0} in 24h` },
    { key:'words', label:'Avg Word Count', displayValue:s?.articles?.avg_word_count || '—', icon:AlignLeft, iconBg:'bg-white/[0.06]', iconColor:'text-[#94A3B8]', trend:null, sub:'Target: 700+' },
    { key:'gentime', label:'Avg Gen Time', displayValue:fmtDuration(s?.articles?.avg_generation_seconds), icon:Clock, iconBg:'bg-purple-500/10', iconColor:'text-purple-400', trend:null, sub:'Last 24h' },
    { key:'throughput', label:'Throughput', displayValue:s?.articles?.throughput_per_hour || '—', icon:TrendingUp, iconBg:'bg-emerald-500/10', iconColor:'text-emerald-400', trend:4, sub:'Articles/hour' },
    { key:'depth', label:'Queue Depth', displayValue:s?.queue || '—', icon:Layers, iconBg:'bg-amber-400/10', iconColor:'text-amber-400', trend:null, sub:s?.queue > 0 ? 'Processing' : 'Idle' },
    { key:'success', label:'Success Rate (24h)', displayValue:(s?.last_24h?.success_rate || 0)+'%', icon:CheckCircle, iconBg:'bg-emerald-500/10', iconColor:'text-emerald-400', trend:3, sub:'All time avg' },
    { key:'quality', label:'Quality Passed', displayValue:s?.quality?.passed || '—', icon:ShieldCheck, iconBg:'bg-teal-500/10', iconColor:'text-teal-400', trend:null, sub:`${s?.quality?.failed || 0} failed checks` },
    { key:'images', label:'Images', displayValue:s?.images?.total_with_images || '—', icon:Image, iconBg:'bg-indigo-500/10', iconColor:'text-indigo-400', trend:null, sub:`${s?.images?.ai_generated || 0} AI · ${s?.images?.from_sources || 0} Src` },
  ]
})

// Stub icons for stats
const FileText = { render: () => h('svg',{class:'w-4 h-4',viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'2'},[h('path',{d:'M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z'}),h('polyline',{points:'14 2 14 8 20 8'})]) }
const FolderOpen = { render: () => h('svg',{class:'w-4 h-4',viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'2'},[h('path',{d:'m6 14 1.5-2.5A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.54 6a2 2 0 0 1-1.95 1.5H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.72a2 2 0 0 1 1.79 1.11L12 8h8a2 2 0 0 1 2 2v4'})]) }
const AlignLeft = { render: () => h('svg',{class:'w-4 h-4',viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'2'},[h('line',{x1:'21',y1:'10',x2:'3',y2:'10'}),h('line',{x1:'21',y1:'6',x2:'3',y2:'6'}),h('line',{x1:'21',y1:'14',x2:'3',y2:'14'}),h('line',{x1:'21',y1:'18',x2:'3',y2:'18'})]) }
const Clock = { render: () => h('svg',{class:'w-4 h-4',viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'2'},[h('circle',{cx:'12',cy:'12',r:'10'}),h('polyline',{points:'12 6 12 12 16 14'})]) }
const Layers = { render: () => h('svg',{class:'w-4 h-4',viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'2'},[h('polygon',{points:'12 2 2 7 12 12 22 7 12 2'}),h('polyline',{points:'2 17 12 22 22 17'}),h('polyline',{points:'2 12 12 17 22 12'})]) }
const ShieldCheck = { render: () => h('svg',{class:'w-4 h-4',viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'2'},[h('path',{d:'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10'}),h('path',{d:'m9 12 2 2 4-4'})]) }
const Image = { render: () => h('svg',{class:'w-4 h-4',viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'2'},[h('rect',{x:'3',y:'3',width:'18',height:'18',rx:'2',ry:'2'}),h('circle',{cx:'8.5',cy:'8.5',r:'1.5'}),h('polyline',{points:'21 15 16 10 5 21'})]) }

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
