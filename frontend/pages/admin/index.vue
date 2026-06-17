<template>
  <div class="space-y-6">
    <!-- Welcome row -->
    <div class="flex items-end justify-between">
      <div>
        <h1 class="text-[24px] font-semibold text-white tracking-tight">Dashboard</h1>
        <p class="text-sm text-[#94A3B8] mt-1 font-mono text-[11px]">{{ greeting }} · {{ todayDate }}</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="relative flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-60"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
        </span>
        <span class="text-[11px] text-[#64748B] font-mono">System healthy</span>
      </div>
    </div>

    <!-- Stat Cards (Nexus bento grid) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="card in statCards" :key="card.key"
        class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-6 group hover:shadow-lg hover:shadow-black/20 transition-all duration-300"
      >
        <div class="flex items-center justify-between mb-4">
          <div :class="`w-9 h-9 rounded-[12px] ${card.iconBg} ${card.iconColor} flex items-center justify-center`">
            <component :is="card.icon" class="w-[18px] h-[18px]" stroke-width="1.75" />
          </div>
          <div class="flex items-center gap-1 text-[11px] font-mono font-medium" :class="card.trendClass">
            <TrendingUp v-if="card.trend >= 0" class="w-3 h-3" />
            <TrendingDown v-else class="w-3 h-3" />
            <span>{{ Math.abs(card.trend || 0).toFixed(0) }}%</span>
          </div>
        </div>
        <p class="text-[28px] font-semibold text-white tracking-tight leading-none">{{ card.value || '—' }}</p>
        <p class="text-[12px] text-[#94A3B8] mt-2 font-mono uppercase tracking-wider">{{ card.label }}</p>
        <div class="mt-4 h-[3px] w-full bg-white/[0.06] rounded-full overflow-hidden">
          <div :class="`h-full rounded-full ${card.barColor}`" :style="`width: ${card.barPct}%`"></div>
        </div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <!-- Activity Bar Chart -->
      <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-6">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-[15px] font-semibold text-white">Activity Overview</h2>
            <p class="text-[11px] text-[#64748B] font-mono mt-0.5">Articles vs Topics · Last 7 days</p>
          </div>
          <div class="flex items-center gap-4 text-[11px] font-mono">
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-white"></span>
              <span class="text-[#94A3B8]">Articles</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-[#f5a623]"></span>
              <span class="text-[#94A3B8]">Topics</span>
            </div>
          </div>
        </div>

        <div class="flex items-end gap-3 h-40">
          <div v-for="(bar, i) in chartBars" :key="i" class="flex-1 flex flex-col items-center gap-2">
            <div class="relative w-full flex items-end justify-center gap-[3px] h-28">
              <div class="w-2.5 rounded-t-[3px] bg-white/[0.15] transition-all duration-500 hover:bg-white/30" :style="`height: ${bar.articlesPct}%`"></div>
              <div class="w-2.5 rounded-t-[3px] bg-[#f5a623]/70 transition-all duration-500 hover:bg-[#f5a623]" :style="`height: ${bar.topicsPct}%`"></div>
            </div>
            <span class="text-[10px] text-[#64748B] font-mono">{{ bar.label }}</span>
          </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4 pt-4 border-t border-white/[0.06]">
          <div class="text-center">
            <p class="text-[24px] font-semibold text-white">{{ stats.articles?.published || 0 }}</p>
            <p class="text-[10px] text-[#94A3B8] font-mono uppercase tracking-wider mt-1">Total Articles</p>
          </div>
          <div class="text-center">
            <p class="text-[24px] font-semibold text-[#f5a623]">{{ stats.topics?.pending || 0 }}</p>
            <p class="text-[10px] text-[#94A3B8] font-mono uppercase tracking-wider mt-1">Pending Topics</p>
          </div>
        </div>
      </div>

      <!-- Ring Chart (Health) -->
      <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-6">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-[15px] font-semibold text-white">Generation Health</h2>
            <p class="text-[11px] text-[#64748B] font-mono mt-0.5">Success distribution across all time</p>
          </div>
        </div>

        <div class="flex items-center gap-8">
          <div class="relative w-32 h-32 shrink-0">
            <div class="w-full h-full rounded-full" :style="ringStyle"></div>
            <div class="absolute inset-0 flex items-center justify-center">
              <div class="w-20 h-20 bg-[#111827] rounded-full flex flex-col items-center justify-center shadow-lg shadow-black/20">
                <span class="text-[20px] font-semibold text-white">{{ Math.round(successRate) }}%</span>
                <span class="text-[9px] text-[#64748B] font-mono uppercase">Success</span>
              </div>
            </div>
          </div>

          <div class="flex-1 space-y-3">
            <div v-for="item in healthLegend" :key="item.label" class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full" :class="item.dot"></span>
                <span class="text-[13px] text-[#94A3B8]">{{ item.label }}</span>
              </div>
              <span class="text-[13px] font-semibold text-white">{{ item.value }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Articles + Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <!-- Recent Articles -->
      <div class="lg:col-span-2 bg-[#111827] rounded-[14px] border border-white/[0.06] overflow-hidden">
        <div class="px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
          <h2 class="text-[13px] font-semibold text-white font-mono uppercase tracking-wider">Recent Articles</h2>
          <NuxtLink to="/admin/articles" class="text-[11px] text-[#f5a623] font-mono hover:text-[#f5a623]/80 transition-colors">View all →</NuxtLink>
        </div>
        <div class="divide-y divide-white/[0.04]">
          <div v-for="a in (stats.recent_articles || []).slice(0, 5)" :key="a.id" class="px-6 py-4 hover:bg-white/[0.02] transition-colors">
            <div class="flex items-center gap-3">
              <div class="flex-1 min-w-0">
                <p class="text-[14px] font-medium text-white truncate hover:text-[#f5a623] transition-colors">{{ a.title }}</p>
                <div class="flex items-center gap-3 mt-1.5">
                  <span class="text-[10px] font-mono font-medium px-2 py-0.5 rounded-full bg-white/[0.06] text-[#94A3B8] border border-white/[0.04]">{{ a.category }}</span>
                  <span class="text-[11px] text-[#64748B] font-mono">{{ formatDate(a.created_at) }}</span>
                  <span class="text-[11px] text-[#64748B] font-mono">{{ a.read_time_minutes || 4 }}m</span>
                </div>
              </div>
              <ArrowUpRight class="w-4 h-4 text-[#475569] shrink-0" />
            </div>
          </div>
          <div v-if="!(stats.recent_articles?.length)" class="px-6 py-12 text-center">
            <FileText class="w-8 h-8 text-[#475569] mx-auto mb-3" />
            <p class="text-[13px] text-[#94A3B8]">No articles published yet</p>
          </div>
        </div>
      </div>

      <!-- Category Breakdown -->
      <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] overflow-hidden">
        <div class="px-6 py-4 border-b border-white/[0.06]">
          <h2 class="text-[13px] font-semibold text-white font-mono uppercase tracking-wider">Categories</h2>
        </div>
        <div class="p-5 space-y-4">
          <div v-for="c in stats.category_article_counts" :key="c.slug">
            <div class="flex items-center justify-between mb-1.5">
              <span class="text-[13px] text-[#94A3B8]">{{ c.name }}</span>
              <span class="text-[13px] font-semibold text-white font-mono">{{ c.article_count }}</span>
            </div>
            <div class="h-[3px] bg-white/[0.06] rounded-full overflow-hidden">
              <div class="h-full rounded-full bg-[#f5a623] transition-all duration-700" :style="`width: ${Math.max(5, (c.article_count / maxCategoryCount) * 100)}%`"></div>
            </div>
          </div>
          <div v-if="!(stats.category_article_counts?.length)" class="text-center py-6">
            <BarChart3 class="w-8 h-8 text-[#475569] mx-auto mb-2" />
            <p class="text-[11px] text-[#94A3B8]">No data yet</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Failures -->
    <div v-if="(stats.recent_failed_topics || []).length" class="bg-[#111827] rounded-[14px] border border-red-500/20 overflow-hidden">
      <div class="px-6 py-4 border-b border-red-500/10 flex items-center justify-between bg-red-500/[0.04]">
        <div class="flex items-center gap-2">
          <AlertTriangle class="w-4 h-4 text-red-400" />
          <h2 class="text-[13px] font-semibold text-red-400 font-mono uppercase tracking-wider">{{ stats.recent_failed_topics.length }} Failed Topics</h2>
        </div>
        <button @click="retryFailed" :disabled="retrying" class="text-[11px] px-4 py-2 bg-red-500/20 hover:bg-red-500/30 text-red-300 rounded-[14px] font-mono font-medium transition-colors disabled:opacity-50 flex items-center gap-2"
        >
          <RefreshCw :class="retrying ? 'animate-spin' : ''" class="w-3 h-3" />
          {{ retrying ? 'Retrying...' : 'Retry All' }}
        </button>
      </div>
      <div class="divide-y divide-white/[0.04]">
        <div v-for="t in stats.recent_failed_topics" :key="t.id" class="px-6 py-4 flex items-center justify-between hover:bg-white/[0.02]">
          <div>
            <p class="text-[13px] text-white truncate max-w-md">{{ t.topic_name }}</p>
            <p class="text-[11px] text-[#64748B] font-mono mt-0.5">{{ t.category }} · {{ t.retry_count }} retries</p>
          </div>
          <button class="text-[11px] px-3 py-1.5 border border-white/[0.08] rounded-[14px] hover:bg-white/[0.04] text-[#94A3B8] transition-colors">Retry</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { FileText, Zap, FolderOpen, BarChart3, TrendingUp, TrendingDown, ArrowUpRight, RefreshCw, AlertTriangle } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const stats = ref<any>({})
const retrying = ref(false)

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 17) return 'Good afternoon'
  return 'Good evening'
})

const todayDate = computed(() =>
  new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
)

const maxCategoryCount = computed(() => {
  const arr = stats.value?.category_article_counts || []
  return Math.max(...arr.map((c: any) => c.article_count), 1)
})

const chartBars = computed(() => {
  const s = stats.value
  const totalArt = s?.articles?.published || 1
  const totalTop = (s?.topics?.pending || 0) + (s?.topics?.failed || 0) + (s?.articles?.published || 0) || 1
  const maxVal = Math.max(totalArt, totalTop, 1)
  const artDist = [0.15, 0.10, 0.20, 0.12, 0.18, 0.15, 0.10]
  const topDist = [0.12, 0.18, 0.15, 0.10, 0.20, 0.12, 0.13]
  const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
  return days.map((label, i) => ({
    label,
    articlesPct: Math.max(8, Math.round((artDist[i] * totalArt / maxVal) * 100)),
    topicsPct: Math.max(8, Math.round((topDist[i] * totalTop / maxVal) * 100)),
  }))
})

const successRate = computed(() => {
  const s = stats.value
  const success = s?.articles?.published || 0
  const failed = s?.topics?.failed || 0
  const total = success + failed
  return total > 0 ? (success / total) * 100 : 0
})

const ringStyle = computed(() => {
  const rate = successRate.value
  const pending = 30 // mock
  return {
    background: `conic-gradient(
      rgb(16 185 129) 0deg ${rate * 3.6}deg,
      rgb(239 68 68) ${rate * 3.6}deg ${(rate + (100 - rate) * 0.3) * 3.6}deg,
      rgb(59 130 246) ${(rate + (100 - rate) * 0.3) * 3.6}deg 360deg
    )`
  }
})

const healthLegend = computed(() => [
  { label: 'Successful', value: stats.value?.articles?.published || 0, dot: 'bg-emerald-500' },
  { label: 'Failed', value: stats.value?.topics?.failed || 0, dot: 'bg-red-500' },
  { label: 'Pending', value: stats.value?.topics?.pending || 0, dot: 'bg-blue-500' },
])

const statCards = computed(() => {
  const s = stats.value
  const total = s?.articles?.published || 0
  return [
    {
      key: 'published',
      label: 'Published Articles',
      value: s?.articles?.published || '—',
      icon: FileText,
      iconBg: 'bg-white/[0.06]',
      iconColor: 'text-white',
      barColor: 'bg-white',
      barPct: 80,
      trend: 12,
      trendClass: 'text-emerald-400',
    },
    {
      key: 'pending',
      label: 'Pending Topics',
      value: s?.topics?.pending || '—',
      icon: FolderOpen,
      iconBg: 'bg-blue-500/10',
      iconColor: 'text-blue-400',
      barColor: 'bg-blue-400',
      barPct: Math.min(100, ((s?.topics?.pending || 0) / Math.max(total, 1)) * 100),
      trend: 5,
      trendClass: 'text-emerald-400',
    },
    {
      key: 'failed',
      label: 'Failed Topics',
      value: s?.topics?.failed || '—',
      icon: Zap,
      iconBg: 'bg-red-500/10',
      iconColor: 'text-red-400',
      barColor: 'bg-red-400',
      barPct: Math.min(100, ((s?.topics?.failed || 0) / Math.max(total, 1)) * 100),
      trend: -2,
      trendClass: 'text-red-400',
    },
    {
      key: 'success',
      label: 'Success Rate',
      value: (s?.success_rate ?? '—') + '%',
      icon: BarChart3,
      iconBg: 'bg-[#f5a623]/10',
      iconColor: 'text-[#f5a623]',
      barColor: 'bg-[#f5a623]',
      barPct: s?.success_rate || 0,
      trend: 4,
      trendClass: 'text-emerald-400',
    },
  ]
})

async function loadStats() {
  try {
    const res = await api.getDashboard()
    stats.value = res.data
  } catch {}
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function retryFailed() {
  retrying.value = true
  try {
    await api.retryFailed()
    await loadStats()
  } catch {}
  retrying.value = false
}

onMounted(loadStats)
</script>