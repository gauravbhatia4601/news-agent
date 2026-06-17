<template>
  <div class="space-y-8">
    <!-- Welcome + time -->
    <div class="flex items-end justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Dashboard</h1>
        <p class="text-sm text-gray-500 mt-1">{{ greeting }} · {{ todayDate }}</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="relative flex h-2.5 w-2.5">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
        </span>
        <span class="text-xs text-gray-500">System healthy</span>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="card in statCards" :key="card.key" class="bg-white rounded-2xl border border-gray-200/60 p-6 group hover:shadow-lg hover:shadow-gray-200/50 transition-all duration-300">
        <div class="flex items-start justify-between mb-4">
          <div :class="`w-10 h-10 rounded-xl ${card.iconBg} ${card.iconColor} flex items-center justify-center`">
            <component :is="card.icon" class="w-5 h-5" stroke-width="1.75" />
          </div>
          <div class="flex items-center gap-1 text-xs font-medium" :class="card.trendClass">
            <TrendingUp v-if="card.trend >= 0" class="w-3.5 h-3.5" />
            <TrendingDown v-else class="w-3.5 h-3.5" />
            <span>{{ Math.abs(card.trend || 0).toFixed(0) }}%</span>
          </div>
        </div>
        <p class="text-2xl font-bold text-gray-900 tracking-tight">{{ card.value || '—' }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ card.label }}</p>
        <div class="mt-4 h-1 w-full bg-gray-100 rounded-full overflow-hidden">
          <div :class="`h-full rounded-full ${card.barColor}`" :style="`width: ${card.barPct}%`"></div>
        </div>
      </div>
    </div>

    <!-- Main Content: Articles + Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Recent Articles -->
      <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200/60 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">Recent Articles</h2>
          <NuxtLink to="/admin/articles" class="text-xs text-[#1a2233] font-medium hover:underline underline-offset-4">View all</NuxtLink>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="(a, i) in (stats.recent_articles || []).slice(0, 5)" :key="a.id" class="px-6 py-4 hover:bg-gray-50/50 transition-colors group">
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center shrink-0 text-xl">
                {{ [ '🔬', '🏛️', '⚖️', '🏗️', '🚀', '🌍', '💰', '⚡' ][i % 8] }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate group-hover:text-[#1a2233] transition-colors">
                  {{ a.title }}
                </p>
                <div class="flex items-center gap-3 mt-1.5">
                  <span class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-gray-100 text-gray-600">{{ a.category }}</span>
                  <span class="text-xs text-gray-400">{{ formatDate(a.created_at) }}</span>
                  <span class="text-xs text-gray-400">{{ a.read_time_minutes || 4 }}m read</span>
                </div>
              </div>
              <ArrowUpRight class="w-4 h-4 text-gray-300 group-hover:text-gray-500 transition-colors shrink-0 mt-1" />
            </div>
          </div>
          <div v-if="!(stats.recent_articles?.length)" class="px-6 py-12 text-center">
            <FileText class="w-10 h-10 text-gray-200 mx-auto mb-3" />
            <p class="text-sm text-gray-400">No articles published yet</p>
          </div>
        </div>
      </div>

      <!-- Right Column -->
      <div class="space-y-6">
        <!-- Category Breakdown -->
        <div class="bg-white rounded-2xl border border-gray-200/60 overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-900">Category Breakdown</h2></div>
          <div class="p-6 space-y-4">
            <div v-for="c in stats.category_article_counts" :key="c.slug">
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-sm text-gray-700">{{ c.name }}</span>
                <span class="text-sm font-medium text-gray-900">{{ c.article_count }}</span>
              </div>
              <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full rounded-full bg-[#1a2233] transition-all duration-700" :style="`width: ${Math.max(5, (c.article_count / maxCategoryCount) * 100)}%`"></div>
              </div>
            </div>
            <div v-if="!(stats.category_article_counts?.length)" class="text-center py-6">
              <BarChart3 class="w-8 h-8 text-gray-200 mx-auto mb-2" />
              <p class="text-xs text-gray-400">No data yet</p>
            </div>
          </div>
        </div>

        <!-- Generation Model Card -->
        <div class="bg-[#1a2233] rounded-2xl p-6 text-white relative overflow-hidden">
          <div class="absolute top-0 right-0 w-24 h-24 bg-[#f5a623]/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
          <div class="relative">
            <div class="flex items-center gap-2 mb-2">
              <Cpu class="w-4 h-4 text-[#f5a623]" />
              <span class="text-[11px] uppercase tracking-wider text-gray-400">AI Model</span>
            </div>
            <p class="text-lg font-bold tracking-tight">gemma4:31b-cloud</p>
            <p class="text-xs text-gray-400 mt-1">Via Ollama · ~400–600 words/article</p>
            <div class="mt-4 flex items-center gap-3">
              <div class="text-xs px-2.5 py-1 rounded-md bg-emerald-500/20 text-emerald-400 font-medium">Active</div>
              <span class="text-xs text-gray-500">{{ stats.success_rate || '—' }}% success</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Failures Panel -->
    <div v-if="(stats.recent_failed_topics || []).length" class="bg-white rounded-2xl border border-red-200/60 overflow-hidden">
      <div class="px-6 py-4 border-b border-red-100 flex items-center justify-between bg-red-50/50">
        <div class="flex items-center gap-2">
          <AlertTriangle class="w-4 h-4 text-red-500" />
          <h2 class="text-sm font-semibold text-red-700">{{ stats.recent_failed_topics.length }} Failed Topics</h2>
        </div>
        <button @click="retryFailed" :disabled="retrying" class="text-xs px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-medium transition-colors disabled:opacity-50 flex items-center gap-2"
        >
          <RefreshCw :class="retrying ? 'animate-spin' : ''" class="w-3 h-3" />
          {{ retrying ? 'Retrying...' : 'Retry All' }}
        </button>
      </div>
      <div class="divide-y divide-gray-50">
        <div v-for="t in stats.recent_failed_topics" :key="t.id" class="px-6 py-4 flex items-center justify-between hover:bg-gray-50/50">
          <div>
            <p class="text-sm text-gray-900 truncate max-w-md">{{ t.topic_name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ t.category }} · {{ t.retry_count }} retries</p>
          </div>
          <button class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-600">Retry</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { FileText, Zap, FolderOpen, BarChart3, TrendingUp, TrendingDown, ArrowUpRight, RefreshCw, Cpu, AlertTriangle } from 'lucide-vue-next'

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

const statCards = computed(() => {
  const s = stats.value
  const total = s?.articles?.published || 0
  return [
    {
      key: 'published',
      label: 'Published Articles',
      value: s?.articles?.published || '—',
      icon: FileText,
      iconBg: 'bg-[#1a2233]/10',
      iconColor: 'text-[#1a2233]',
      barColor: 'bg-[#1a2233]',
      barPct: 80,
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
      barColor: 'bg-blue-500',
      barPct: Math.min(100, ((s?.topics?.pending || 0) / Math.max(total, 1)) * 100),
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
      barColor: 'bg-red-500',
      barPct: Math.min(100, ((s?.topics?.failed || 0) / Math.max(total, 1)) * 100),
      trend: -2,
      trendClass: 'text-red-500',
    },
    {
      key: 'success',
      label: 'Success Rate',
      value: (s?.success_rate ?? '—') + '%',
      icon: BarChart3,
      iconBg: 'bg-amber-50',
      iconColor: 'text-[#d4981e]',
      barColor: 'bg-[#f5a623]',
      barPct: s?.success_rate || 0,
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