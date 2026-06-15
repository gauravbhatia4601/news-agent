<template>
  <div class="space-y-6">
    <!-- Stats cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="stat in statsCards" :key="stat.label" class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500 font-semibold">{{ stat.label }}</p>
        <p class="text-2xl font-bold mt-1" :class="stat.color">{{ stat.value }}</p>
        <p v-if="stat.sub" class="text-xs text-slate-400 mt-1">{{ stat.sub }}</p>
      </div>
    </div>

    <!-- Two columns -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Recent Articles -->
      <div class="bg-white rounded-lg border border-slate-200">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
          <h2 class="font-semibold text-sm">Recent Articles</h2>
          <NuxtLink to="/admin/articles" class="text-xs text-blue-600 hover:underline">View all</NuxtLink>
        </div>
        <div class="divide-y divide-slate-100">
          <NuxtLink v-for="a in stats.recent_articles" :key="a.id" :to="`/admin/articles/${a.id}`" class="block px-4 py-3 hover:bg-slate-50 transition-colors">
            <p class="text-sm font-medium truncate">{{ a.title }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ a.category }} · {{ formatDate(a.created_at) }}</p>
          </NuxtLink>
          <div v-if="!stats.recent_articles?.length" class="px-4 py-6 text-center text-slate-400 text-sm">No articles yet</div>
        </div>
      </div>

      <!-- Generation activity -->
      <div class="bg-white rounded-lg border border-slate-200">
        <div class="px-4 py-3 border-b border-slate-100">
          <h2 class="font-semibold text-sm">Category Breakdown</h2>
        </div>
        <div class="divide-y divide-slate-100">
          <div v-for="c in stats.category_article_counts" :key="c.slug" class="flex items-center justify-between px-4 py-3">
            <span class="text-sm">{{ c.name }}</span>
            <span class="text-sm font-medium text-slate-600">{{ c.article_count }}</span>
          </div>
          <div v-if="!stats.category_article_counts?.length" class="px-4 py-6 text-center text-slate-400 text-sm">No data yet</div>
        </div>
      </div>
    </div>

    <!-- Queue status bar -->
    <div class="bg-white rounded-lg border border-slate-200 p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-sm">Queue Status</h2>
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full" :class="stats.queue_jobs > 0 ? 'bg-green-500 animate-pulse' : 'bg-slate-300'" />
          <span class="text-xs text-slate-500">{{ stats.queue_jobs > 0 ? 'Processing' : 'Idle' }}</span>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div class="text-center p-3 bg-slate-50 rounded-md">
          <p class="text-2xl font-bold text-blue-600">{{ stats.topics?.pending || 0 }}</p>
          <p class="text-xs text-slate-500">Pending</p>
        </div>
        <div class="text-center p-3 bg-slate-50 rounded-md">
          <p class="text-2xl font-bold text-emerald-600">{{ stats.topics?.generated || 0 }}</p>
          <p class="text-xs text-slate-500">Generated</p>
        </div>
        <div class="text-center p-3 bg-slate-50 rounded-md">
          <p class="text-2xl font-bold text-red-600">{{ stats.topics?.failed || 0 }}</p>
          <p class="text-xs text-slate-500">Failed</p>
        </div>
      </div>
    </div>

    <!-- Failures -->
    <div v-if="stats.recent_failed_topics?.length" class="bg-white rounded-lg border border-red-200">
      <div class="px-4 py-3 border-b border-red-100 flex items-center justify-between bg-red-50">
        <h2 class="font-semibold text-sm text-red-700">Recent Failures</h2>
        <button @click="retryFailed" :disabled="retrying" class="text-xs px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50">{{ retrying ? 'Retrying...' : 'Retry All' }}</button>
      </div>
      <div class="divide-y divide-slate-100">
        <div v-for="t in stats.recent_failed_topics" :key="t.id" class="px-4 py-3">
          <p class="text-sm truncate">{{ t.topic_name }}</p>
          <p class="text-xs text-slate-500">{{ t.category }} · retries: {{ t.retry_count }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const stats = ref<any>({})
const retrying = ref(false)

const statsCards = computed(() => [
  { label: 'Published Articles', value: stats.value.articles?.published ?? '—', sub: 'Total', color: '' },
  { label: 'Topics Pending', value: stats.value.topics?.pending ?? '—', sub: 'In queue', color: 'text-blue-600' },
  { label: 'Failed', value: stats.value.topics?.failed ?? '—', sub: 'Need attention', color: 'text-red-600' },
  { label: 'Success Rate', value: (stats.value.success_rate ?? '—') + '%', sub: 'All time', color: parseFloat(stats.value.success_rate || '0') > 80 ? 'text-emerald-600' : 'text-amber-600' },
])

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
