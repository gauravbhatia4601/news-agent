<template>
  <div class="space-y-6">

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
      <div v-for="card in statCards" :key="card.key" class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5 hover:shadow-md transition-all duration-300">
        <div class="flex items-center justify-between mb-4">
          <div :class="`w-9 h-9 rounded-lg ${card.iconBg} ${card.iconColor} flex items-center justify-center`">
            <component :is="card.icon" class="w-4 h-4" stroke-width="1.75" />
          </div>
          <div v-if="card.trend != null" class="flex items-center gap-1 text-xs font-medium" :class="card.trend >= 0 ? 'text-emerald-600' : 'text-red-600'">
            <TrendingUp v-if="card.trend >= 0" class="w-3 h-3" />
            <TrendingDown v-else class="w-3 h-3" />
            <span>{{ Math.abs(card.trend).toFixed(0) }}%</span>
          </div>
        </div>
        <p class="text-2xl font-bold text-admin-text tracking-tight">{{ card.displayValue }}</p>
        <p class="text-xs text-admin-muted mt-1">{{ card.label }}</p>
        <p v-if="card.sub" class="text-[11px] text-slate-400 mt-1">{{ card.sub }}</p>
      </div>
    </div>

    <!-- Two Column Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Model Usage -->
      <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-[15px] font-semibold text-admin-text">Model Usage</h2>
          <span class="text-xs text-admin-muted">Last 30 days</span>
        </div>
        <div class="space-y-4">
          <div v-for="model in stats.models" :key="model.model">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm text-admin-muted">{{ model.model || 'Unknown' }}</span>
              <span class="text-xs font-medium text-admin-text">{{ model.count }}</span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full bg-admin-accent transition-all duration-700" :style="`width: ${modelPct(model.count)}%`"></div>
            </div>
          </div>
          <div v-if="!(stats.models?.length)" class="text-center py-8">
            <Cpu class="w-8 h-8 text-slate-400 mx-auto mb-2" />
            <p class="text-sm text-admin-muted">No model data yet</p>
          </div>
        </div>
      </div>

      <!-- Top Categories -->
      <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-[15px] font-semibold text-admin-text">Top Categories</h2>
          <span class="text-xs text-admin-muted">Last 30 days</span>
        </div>
        <div class="space-y-4">
          <div v-for="cat in stats.top_categories" :key="cat.slug">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm text-admin-muted">{{ cat.name }}</span>
              <span class="text-xs font-medium text-admin-text">{{ cat.count }}</span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full bg-admin-accent transition-all duration-700" :style="`width: ${catPct(cat.count)}%`"></div>
            </div>
          </div>
          <div v-if="!(stats.top_categories?.length)" class="text-center py-8">
            <BarChart3 class="w-8 h-8 text-slate-400 mx-auto mb-2" />
            <p class="text-sm text-admin-muted">No category data yet</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Queue Health -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-[15px] font-semibold text-admin-text">Queue Status</h2>
        <NuxtLink to="/admin/queue" class="text-xs text-admin-accent font-medium hover:underline underline-offset-4">View Details →</NuxtLink>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="flex items-center gap-3 p-4 rounded-[14px] bg-slate-50 border border-admin-border">
          <div class="w-2.5 h-2.5 rounded-full" :class="queue.pending_jobs > 0 ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'" />
          <div>
            <p class="text-xs text-admin-muted">Pending Jobs</p>
            <p class="text-lg font-bold text-admin-text">{{ queue.pending_jobs || 0 }}</p>
          </div>
        </div>
        <div class="flex items-center gap-3 p-4 rounded-[14px] bg-slate-50 border border-admin-border">
          <div class="w-2.5 h-2.5 rounded-full bg-red-500" />
          <div>
            <p class="text-xs text-admin-muted">Failed Jobs</p>
            <p class="text-lg font-bold text-admin-text">{{ queue.total_failed_jobs || 0 }}</p>
          </div>
        </div>
        <div class="flex items-center gap-3 p-4 rounded-[14px] bg-slate-50 border border-admin-border">
          <div class="w-2.5 h-2.5 rounded-full" :class="parseFloat(stats.last_24h?.success_rate || '0') > 80 ? 'bg-emerald-500' : 'bg-amber-400'" />
          <div>
            <p class="text-xs text-admin-muted">24h Success Rate</p>
            <p class="text-lg font-bold text-admin-text">{{ stats.last_24h?.success_rate || 0 }}%</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Failures -->
    <div v-if="queue.recent_failed?.length" class="bg-admin-surface rounded-[14px] border border-red-200 overflow-hidden shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div class="px-6 py-4 border-b border-red-200 bg-red-50 flex items-center gap-2">
        <AlertTriangle class="w-4 h-4 text-red-600" />
        <h2 class="text-[15px] font-semibold text-red-700">Recent Failed Jobs</h2>
      </div>
      <div class="divide-y divide-slate-100">
        <div v-for="f in queue.recent_failed" :key="f.id" class="px-6 py-4 flex items-start gap-4">
          <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center shrink-0"><XCircle class="w-4 h-4 text-red-600" /></div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
              <span class="text-xs text-admin-muted">Job #{{ f.id }} · {{ f.queue }}</span>
              <span class="text-xs text-slate-400">{{ f.failed_at }}</span>
            </div>
            <p class="text-xs text-red-600 mt-1 line-clamp-2">{{ f.exception }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Loader2, RotateCcw, TrendingUp, TrendingDown, Cpu, BarChart3, Zap, CheckCircle, AlertCircle, AlertTriangle, XCircle } from 'lucide-vue-next'
import { h } from 'vue'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const stats = ref<any>({})
const queue = ref<any>({})
const retrying = ref(false)
const actionMessage = ref<{ type: string; text: string } | null>(null)

const statCards = computed(() => {
  const s = stats.value
  const total = s?.articles?.published || 0
  return [
    { key:'published', label:'Published Articles', displayValue:s?.articles?.published || '—', icon:FileText, iconBg:'bg-admin-sidebar/5', iconColor:'text-admin-accent', trend:12, sub:`+${s?.articles?.last_24h || 0} in 24h` },
    { key:'pending', label:'Pending Topics', displayValue:s?.topics?.pending || '—', icon:FolderOpen, iconBg:'bg-blue-50', iconColor:'text-blue-600', trend:5, sub:`${s?.topics?.discovered_24h || 0} discovered today` },
    { key:'failed', label:'Failed', displayValue:s?.topics?.failed || '—', icon:XCircle, iconBg:'bg-red-50', iconColor:'text-red-600', trend:-2, sub:`${s?.last_24h?.failed || 0} in 24h` },
    { key:'words', label:'Avg Word Count', displayValue:s?.articles?.avg_word_count || '—', icon:AlignLeft, iconBg:'bg-slate-100', iconColor:'text-admin-muted', trend:null, sub:'Target: 700+' },
    { key:'gentime', label:'Avg Gen Time', displayValue:fmtDuration(s?.articles?.avg_generation_seconds), icon:Clock, iconBg:'bg-purple-50', iconColor:'text-purple-600', trend:null, sub:'Last 24h' },
    { key:'throughput', label:'Throughput', displayValue:s?.articles?.throughput_per_hour || '—', icon:TrendingUp, iconBg:'bg-emerald-50', iconColor:'text-emerald-600', trend:4, sub:'Articles/hour' },
    { key:'depth', label:'Queue Depth', displayValue:s?.queue || '—', icon:Layers, iconBg:'bg-amber-50', iconColor:'text-admin-accent', trend:null, sub:s?.queue > 0 ? 'Processing' : 'Idle' },
    { key:'success', label:'Success Rate (24h)', displayValue:(s?.last_24h?.success_rate || 0)+'%', icon:CheckCircle, iconBg:'bg-emerald-50', iconColor:'text-emerald-600', trend:3, sub:'All time avg' },
    { key:'quality', label:'Quality Passed', displayValue:s?.quality?.passed || '—', icon:ShieldCheck, iconBg:'bg-teal-50', iconColor:'text-teal-600', trend:null, sub:`${s?.quality?.failed || 0} failed checks` },
    { key:'images', label:'Images', displayValue:s?.images?.total_with_images || '—', icon:Image, iconBg:'bg-indigo-50', iconColor:'text-indigo-600', trend:null, sub:`${s?.images?.ai_generated || 0} AI · ${s?.images?.from_sources || 0} Src` },
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
