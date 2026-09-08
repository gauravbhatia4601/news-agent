<template>
  <div class="space-y-6">
    <!-- Welcome row -->
    <div class="flex items-end justify-between">
      <div>
        <h1 class="text-[22px] font-semibold text-admin-text tracking-tight">Dashboard</h1>
        <p class="text-sm text-admin-muted mt-1">{{ greeting }} · {{ todayDate }}</p>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
      <div v-for="card in statCards" :key="card.key"
        class="bg-admin-surface rounded-[14px] border border-admin-border/80 p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
      >
        <div class="flex items-center justify-between mb-4">
          <div :class="`w-9 h-9 rounded-[12px] ${card.iconBg} ${card.iconColor} flex items-center justify-center`">
            <component :is="card.icon" class="w-[18px] h-[18px]" stroke-width="1.75" />
          </div>
          <div class="flex items-center gap-1 text-[11px] font-medium" :class="card.trendClass">
            <TrendingUp v-if="card.trend >= 0" class="w-3 h-3" />
            <TrendingDown v-else class="w-3 h-3" />
            <span>{{ Math.abs(card.trend || 0).toFixed(0) }}%</span>
          </div>
        </div>
        <p class="text-[28px] font-semibold text-admin-text tracking-tight leading-none">{{ card.value || '—' }}</p>
        <p class="text-[12px] text-admin-muted mt-2 uppercase tracking-wider">{{ card.label }}</p>
      </div>
    </div>

    <!-- Line Chart + Categories -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <!-- Activity Line Chart -->
      <div class="lg:col-span-2 bg-admin-surface rounded-[14px] border border-admin-border/80 p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-[15px] font-semibold text-admin-text">Activity Overview</h2>
            <p class="text-[11px] text-slate-400 mt-0.5">Articles vs Topics · Last 7 days</p>
          </div>
          <div class="flex items-center gap-4 text-[11px]">
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-slate-700"></span>
              <span class="text-admin-muted">Articles</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-admin-accent"></span>
              <span class="text-admin-muted">Topics</span>
            </div>
          </div>
        </div>

        <div class="relative w-full" style="aspect-ratio: 3/1" @mouseleave="hoveredPoint = null">
          <svg viewBox="0 0 600 200" class="w-full h-full block" preserveAspectRatio="xMidYMid meet">
            <defs>
              <linearGradient id="articlesGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#334155" stop-opacity="0.18" />
                <stop offset="100%" stop-color="#334155" stop-opacity="0" />
              </linearGradient>
              <linearGradient id="topicsGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#f5a623" stop-opacity="0.22" />
                <stop offset="100%" stop-color="#f5a623" stop-opacity="0" />
              </linearGradient>
            </defs>

            <!-- Grid lines -->
            <line v-for="n in 3" :key="'g-' + n" x1="40" :y1="20 + n * 50" x2="580" :y2="20 + n * 50" stroke="#f1f5f9" stroke-width="1" />

            <!-- Y-axis labels -->
            <text x="34" y="24" text-anchor="end" class="text-[10px] fill-slate-400">{{ yAxisMax }}</text>
            <text x="34" y="99" text-anchor="end" class="text-[10px] fill-slate-400">{{ Math.round(yAxisMax / 2) }}</text>
            <text x="34" y="174" text-anchor="end" class="text-[10px] fill-slate-400">0</text>

            <!-- Areas -->
            <path :d="areaPath('articles')" fill="url(#articlesGrad)" />
            <path :d="areaPath('topics')" fill="url(#topicsGrad)" />

            <!-- Lines -->
            <path :d="linePath('articles')" fill="none" stroke="#334155" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path :d="linePath('topics')" fill="none" stroke="#f5a623" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />

            <!-- Hover crosshair -->
            <line v-if="hoveredPoint" :x1="hoveredPoint.x" y1="20" :x2="hoveredPoint.x" y2="170" stroke="#cbd5e1" stroke-width="1" stroke-dasharray="3 3" />

            <!-- Points -->
            <g v-for="(p, i) in chartPoints" :key="'a-' + i">
              <circle :cx="p.x" :cy="p.articlesY" r="3" fill="#334155" />
              <circle :cx="p.x" :cy="p.articlesY" r="7" fill="transparent" class="cursor-pointer" @mouseenter="setHover(i, 'articles')" @mousemove="setHover(i, 'articles')" />
            </g>
            <g v-for="(p, i) in chartPoints" :key="'t-' + i">
              <circle :cx="p.x" :cy="p.topicsY" r="3" fill="#f5a623" />
              <circle :cx="p.x" :cy="p.topicsY" r="7" fill="transparent" class="cursor-pointer" @mouseenter="setHover(i, 'topics')" @mousemove="setHover(i, 'topics')" />
            </g>

            <!-- Hover columns -->
            <rect v-for="(p, i) in chartPoints" :key="'col-' + i"
              :x="i === 0 ? 40 : p.x - 45"
              y="20"
              :width="i === 0 || i === chartPoints.length - 1 ? 45 : 90"
              height="150"
              fill="transparent"
              class="cursor-crosshair"
              @mouseenter="setHover(i, preferredSeries(i))"
              @mousemove="setHover(i, preferredSeries(i))"
            />

            <!-- X-axis labels -->
            <text v-for="p in chartPoints" :key="'xl-' + p.index" :x="p.x" y="192" text-anchor="middle" class="text-[10px] fill-slate-400">{{ p.label }}</text>
          </svg>

          <!-- Tooltip -->
          <div v-if="hoveredPoint"
            class="absolute z-10 pointer-events-none bg-admin-sidebar text-white text-[11px] px-3 py-2 rounded-[12px] shadow-xl transform -translate-x-1/2 -translate-y-full mt-[-10px] min-w-[120px]"
            :style="tooltipStyle"
          >
            <div class="text-[10px] text-slate-300 mb-1">{{ chartPoints[hoveredPoint.index]?.label }}</div>
            <div class="flex items-center justify-between gap-3">
              <span class="flex items-center gap-1.5 text-slate-300">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Articles
              </span>
              <span class="font-semibold">{{ chartPoints[hoveredPoint.index]?.articles }}</span>
            </div>
            <div class="flex items-center justify-between gap-3 mt-0.5">
              <span class="flex items-center gap-1.5 text-slate-300">
                <span class="w-1.5 h-1.5 rounded-full bg-admin-accent"></span> Topics
              </span>
              <span class="font-semibold">{{ chartPoints[hoveredPoint.index]?.topics }}</span>
            </div>
            <div class="absolute bottom-[-4px] left-1/2 -translate-x-1/2 w-2 h-2 bg-admin-sidebar rotate-45"></div>
          </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4 pt-4 border-t border-admin-border">
          <div class="text-center">
            <p class="text-[24px] font-semibold text-admin-text">{{ stats.articles?.published || 0 }}</p>
            <p class="text-[10px] text-admin-muted uppercase tracking-wider mt-1">Total Articles</p>
          </div>
          <div class="text-center">
            <p class="text-[24px] font-semibold text-admin-accent">{{ stats.topics?.pending || 0 }}</p>
            <p class="text-[10px] text-admin-muted uppercase tracking-wider mt-1">Pending Topics</p>
          </div>
        </div>
      </div>

      <!-- Category Breakdown -->
      <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] overflow-hidden">
        <div class="px-6 py-4 border-b border-admin-border">
          <h2 class="text-[13px] font-semibold text-admin-text uppercase tracking-wider">Categories</h2>
        </div>
        <div class="p-5 space-y-4">
          <div v-for="c in stats.category_article_counts" :key="c.slug">
            <div class="flex items-center justify-between mb-1.5">
              <span class="text-[13px] text-slate-600">{{ c.name }}</span>
              <span class="text-[13px] font-semibold text-admin-text">{{ c.article_count }}</span>
            </div>
            <div class="h-[3px] bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full bg-admin-accent-ink transition-all duration-700" :style="`width: ${Math.max(5, (c.article_count / maxCategoryCount) * 100)}%`"></div>
            </div>
          </div>
          <div v-if="!(stats.category_article_counts?.length)" class="text-center py-6">
            <BarChart3 class="w-8 h-8 text-slate-200 mx-auto mb-2" />
            <p class="text-[11px] text-slate-400">No data yet</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Articles -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] overflow-hidden">
      <div class="px-6 py-4 border-b border-admin-border flex items-center justify-between">
        <h2 class="text-[13px] font-semibold text-admin-text uppercase tracking-wider">Recent Articles</h2>
        <NuxtLink to="/admin/articles" class="text-[11px] text-admin-accent-ink font-medium hover:text-slate-700 transition-colors">View all →</NuxtLink>
      </div>
      <div class="divide-y divide-slate-50">
        <div v-for="a in (stats.recent_articles || []).slice(0, 5)" :key="a.id" class="px-6 py-4 hover:bg-slate-50/50 transition-colors">
          <div class="flex items-start gap-3">
            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-medium text-admin-text truncate hover:text-admin-accent-ink transition-colors">{{ a.title }}</p>
              <div class="flex items-center gap-3 mt-1.5">
                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ a.category }}</span>
                <span class="text-[11px] text-slate-400">{{ formatDate(a.created_at) }}</span>
                <span class="text-[11px] text-slate-400">{{ a.read_time_minutes || 4 }}m</span>
              </div>
            </div>
            <ArrowUpRight class="w-4 h-4 text-slate-300 shrink-0 mt-1" />
          </div>
        </div>
        <div v-if="!(stats.recent_articles?.length)" class="px-6 py-12 text-center">
          <FileText class="w-8 h-8 text-slate-200 mx-auto mb-3" />
          <p class="text-[13px] text-slate-400">No articles published yet</p>
        </div>
      </div>
    </div>

    <!-- Failures -->
    <div v-if="(stats.recent_failed_topics || []).length" class="bg-admin-surface rounded-[14px] border border-red-200/60 shadow-[0_1px_2px_rgba(0,0,0,0.04)] overflow-hidden">
      <div class="px-6 py-4 border-b border-red-100 flex items-center justify-between bg-red-50/50">
        <div class="flex items-center gap-2">
          <AlertTriangle class="w-4 h-4 text-red-500" />
          <h2 class="text-[13px] font-semibold text-red-700 uppercase tracking-wider">{{ stats.recent_failed_topics.length }} Failed Topics</h2>
        </div>
        <button @click="retryFailed" :disabled="retrying"
          class="text-[11px] px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-[14px] font-medium transition-colors disabled:opacity-50 flex items-center gap-2"
        >
          <RefreshCw :class="retrying ? 'animate-spin' : ''" class="w-3 h-3" />
          {{ retrying ? 'Retrying...' : 'Retry All' }}
        </button>
      </div>
      <div class="divide-y divide-slate-50">
        <div v-for="t in stats.recent_failed_topics" :key="t.id" class="px-6 py-4 flex items-center justify-between hover:bg-slate-50/50">
          <div>
            <p class="text-[13px] text-admin-text truncate max-w-md">{{ t.topic_name }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">{{ t.category }} · {{ t.retry_count }} retries</p>
          </div>
          <button class="text-[11px] px-3 py-1.5 border border-admin-border rounded-[14px] hover:bg-slate-50 text-slate-600 transition-colors">Retry</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { FileText, Zap, FolderOpen, BarChart3, TrendingUp, TrendingDown, ArrowUpRight, RefreshCw, AlertTriangle, Tag } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const stats = ref<any>({})
const retrying = ref(false)
const hoveredPoint = ref<{ series: string; index: number; x: number; y: number; value: number } | null>(null)

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

const yAxisMax = computed(() => {
  const art = stats.value?.daily_trend?.articles || []
  const top = stats.value?.daily_trend?.topics || []
  const max = Math.max(...art, ...top, 0)
  if (max === 0) return 10
  const magnitude = Math.pow(10, Math.floor(Math.log10(max)))
  return Math.ceil(max / magnitude) * magnitude
})

const chartPoints = computed(() => {
  const trend = stats.value?.daily_trend
  const labels = trend?.labels || ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
  const articles = trend?.articles || Array(7).fill(0)
  const topics = trend?.topics || Array(7).fill(0)

  const xMin = 40, xMax = 580, yMin = 20, yMax = 170
  const maxVal = yAxisMax.value

  return labels.map((label: string, i: number) => {
    const x = xMin + (i / (labels.length - 1)) * (xMax - xMin)
    const articlesY = yMax - ((articles[i] || 0) / maxVal) * (yMax - yMin)
    const topicsY = yMax - ((topics[i] || 0) / maxVal) * (yMax - yMin)
    return { index: i, label, x, articlesY, topicsY, articles: articles[i] || 0, topics: topics[i] || 0 }
  })
})

function linePath(series: 'articles' | 'topics') {
  const points = chartPoints.value.map(p => ({
    x: p.x,
    y: series === 'articles' ? p.articlesY : p.topicsY,
  }))
  return smoothPath(points)
}

function areaPath(series: 'articles' | 'topics') {
  const first = chartPoints.value[0]
  const last = chartPoints.value[chartPoints.value.length - 1]
  if (!first || !last) return ''
  const topPath = linePath(series)
  return `${topPath} L ${last.x},170 L ${first.x},170 Z`
}

function smoothPath(points: { x: number; y: number }[]) {
  if (points.length === 0) return ''
  if (points.length === 1) return `M ${points[0].x},${points[0].y}`

  const smoothing = 0.2
  const yMin = 20, yMax = 170
  let d = `M ${points[0].x},${points[0].y}`

  for (let i = 0; i < points.length - 1; i++) {
    const p0 = points[i - 1] || points[i]
    const p1 = points[i]
    const p2 = points[i + 1]
    const p3 = points[i + 2] || p2

    const cp1x = p1.x + (p2.x - p0.x) * smoothing
    const cp1y = Math.min(yMax, Math.max(yMin, p1.y + (p2.y - p0.y) * smoothing))
    const cp2x = p2.x - (p3.x - p1.x) * smoothing
    const cp2y = Math.min(yMax, Math.max(yMin, p2.y - (p3.y - p1.y) * smoothing))

    d += ` C ${cp1x},${cp1y} ${cp2x},${cp2y} ${p2.x},${p2.y}`
  }

  return d
}

function preferredSeries(index: number): 'articles' | 'topics' {
  const p = chartPoints.value[index]
  if (!p) return 'articles'
  return p.topics >= p.articles ? 'topics' : 'articles'
}

function setHover(index: number, series: 'articles' | 'topics') {
  const p = chartPoints.value[index]
  if (!p) return
  const y = series === 'articles' ? p.articlesY : p.topicsY
  hoveredPoint.value = { series, index, x: p.x, y, value: series === 'articles' ? p.articles : p.topics }
}

const tooltipStyle = computed(() => {
  if (!hoveredPoint.value) return {}
  const p = chartPoints.value[hoveredPoint.value.index]
  if (!p) return {}
  const topY = Math.min(p.articlesY, p.topicsY)
  return {
    left: `${(p.x / 600) * 100}%`,
    top: `${(topY / 200) * 100}%`,
  }
})

const totalCategories = computed(() => {
  return stats.value?.category_article_counts?.length || 0
})

const statCards = computed(() => {
  const s = stats.value
  const total = s?.articles?.published || 0
  return [
    {
      key: 'published',
      label: 'Published Articles',
      value: s?.articles?.published || '—',
      icon: FileText,
      iconBg: 'bg-slate-100',
      iconColor: 'text-slate-700',
      trend: 12,
      trendClass: 'text-emerald-600',
    },
    {
      key: 'pending',
      label: 'Pending Topics',
      value: s?.topics?.pending || '—',
      icon: FolderOpen,
      iconBg: 'bg-blue-50',
      iconColor: 'text-blue-600',
      trend: 5,
      trendClass: 'text-emerald-600',
    },
    {
      key: 'failed',
      label: 'Failed Topics',
      value: s?.topics?.failed || '—',
      icon: Zap,
      iconBg: 'bg-red-50',
      iconColor: 'text-red-500',
      trend: -2,
      trendClass: 'text-red-500',
    },
    {
      key: 'categories',
      label: 'Total Categories',
      value: totalCategories.value || '—',
      icon: Tag,
      iconBg: 'bg-amber-50',
      iconColor: 'text-admin-accent',
      trend: 0,
      trendClass: 'text-slate-400',
    },
    {
      key: 'success',
      label: 'Success Rate',
      value: (s?.success_rate ?? '—') + '%',
      icon: BarChart3,
      iconBg: 'bg-emerald-50',
      iconColor: 'text-emerald-600',
      trend: 4,
      trendClass: 'text-emerald-600',
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
