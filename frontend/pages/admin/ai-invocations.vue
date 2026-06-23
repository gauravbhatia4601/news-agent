<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-slate-900/5 flex items-center justify-center"><Cpu class="w-5 h-5 text-amber-600" /></div>
        <div>
          <h1 class="text-[18px] font-semibold text-slate-900">AI Invocations</h1>
          <p class="text-xs text-slate-500">LLM call tracking, token usage, and cost monitoring</p>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
        <p class="text-2xl font-bold text-slate-900 tracking-tight">{{ formatNumber(stats.total_tokens) }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Tokens Used</p>
        <p class="text-[11px] text-slate-400 mt-1">{{ formatNumber(stats.total_prompt_tokens) }} prompt · {{ formatNumber(stats.total_completion_tokens) }} completion</p>
      </div>
      <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
        <p class="text-2xl font-bold text-slate-900 tracking-tight">{{ stats.total_invocations ?? 0 }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Calls</p>
        <p class="text-[11px] text-slate-400 mt-1"><span class="text-emerald-600">{{ stats.success_count ?? 0 }}</span> success · <span class="text-red-600">{{ stats.failed_count ?? 0 }}</span> failed</p>
      </div>
      <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
        <p class="text-2xl font-bold text-slate-900 tracking-tight">{{ formatDuration(stats.avg_duration_ms) }}</p>
        <p class="text-xs text-slate-500 mt-1">Avg Duration</p>
      </div>
      <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
        <p class="text-2xl font-bold text-slate-900 tracking-tight">{{ stats.by_provider?.length ?? 0 }}</p>
        <p class="text-xs text-slate-500 mt-1">Models Used</p>
      </div>
    </div>

    <!-- Provider Breakdown -->
    <div v-if="stats.by_provider?.length" class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <h2 class="text-[15px] font-semibold text-slate-900 mb-4">Usage by Provider / Model</h2>
      <div class="space-y-3">
        <div v-for="p in stats.by_provider" :key="`${p.provider}-${p.model}`" class="flex items-center justify-between">
          <div>
            <span class="text-sm font-medium text-slate-900">{{ p.model }}</span>
            <span class="text-xs text-slate-400 ml-2">{{ p.provider }}</span>
          </div>
          <div class="flex items-center gap-4 text-xs">
            <span class="text-slate-500">{{ p.count }} calls</span>
            <span class="font-medium text-slate-900">{{ formatNumber(p.tokens) }} tokens</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Invocations Table -->
    <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4 flex-wrap">
        <h2 class="text-[15px] font-semibold text-slate-900">Invocation Log</h2>
        <div class="flex items-center gap-2">
          <button
            v-for="opt in statusOptions"
            :key="opt.value"
            @click="statusFilter = opt.value"
            class="px-3 py-1.5 rounded-[10px] text-xs font-medium transition-all"
            :class="statusFilter === opt.value ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200'"
          >{{ opt.label }}</button>
        </div>
      </div>

      <div v-if="loading" class="p-12 text-center">
        <Loader2 class="w-8 h-8 text-slate-400 animate-spin mx-auto mb-3" />
        <p class="text-sm text-slate-500">Loading invocations...</p>
      </div>
      <div v-else-if="!invocations.length" class="p-12 text-center">
        <Cpu class="w-8 h-8 text-slate-300 mx-auto mb-3" />
        <p class="text-sm text-slate-500">No AI invocations recorded yet</p>
      </div>
      <div v-else>
        <div class="divide-y divide-slate-100">
          <div v-for="inv in invocations" :key="inv.id" class="px-6 py-3.5 flex items-center gap-4 hover:bg-slate-50 transition-colors">
            <div class="w-2 h-2 rounded-full shrink-0" :class="inv.status === 'success' ? 'bg-emerald-500' : 'bg-red-500'" />
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-slate-900">{{ inv.model || 'Unknown' }}</span>
                <span class="text-xs text-slate-400">{{ inv.provider }}</span>
              </div>
              <p class="text-xs text-slate-400 truncate">
                {{ inv.topic?.topic_name || '—' }}
                <span v-if="inv.error" class="text-red-500"> · {{ inv.error }}</span>
              </p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-xs font-medium text-slate-900">{{ formatNumber(inv.total_tokens) }} tokens</p>
              <p class="text-[11px] text-slate-400">{{ formatNumber(inv.prompt_tokens) }}p · {{ formatNumber(inv.completion_tokens) }}c · {{ formatDuration(inv.duration_ms) }}</p>
            </div>
            <span class="text-[11px] text-slate-400 shrink-0">{{ fmtTime(inv.invoked_at) }}</span>
          </div>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
          <p class="text-xs text-slate-400">{{ total }} invocation{{ total === 1 ? '' : 's' }}</p>
          <div class="flex items-center gap-2">
            <button @click="goPage(page - 1)" :disabled="page <= 1" class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg border border-slate-200 disabled:opacity-40 transition-colors">Prev</button>
            <span class="text-xs text-slate-500">{{ page }} / {{ lastPage }}</span>
            <button @click="goPage(page + 1)" :disabled="page >= lastPage" class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg border border-slate-200 disabled:opacity-40 transition-colors">Next</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Loader2, Cpu } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const invocations = ref<any[]>([])
const stats = ref<any>({})
const loading = ref(true)
const statusFilter = ref<'all' | 'success' | 'failed'>('all')
const page = ref(1)
const total = ref(0)
const lastPage = ref(1)

const statusOptions = [
  { value: 'all' as const, label: 'All' },
  { value: 'success' as const, label: 'Success' },
  { value: 'failed' as const, label: 'Failed' },
]

async function load() {
  loading.value = true
  try {
    const res = await api.getAiInvocations({
      status: statusFilter.value,
      page: page.value,
      per_page: 25,
    })
    invocations.value = res.data.invocations
    stats.value = res.data.stats
    total.value = res.data.total
    lastPage.value = res.data.last_page
  } catch {}
  loading.value = false
}

function goPage(p: number) {
  page.value = Math.max(1, Math.min(p, lastPage.value))
  load()
}

function formatNumber(n: number): string {
  if (!n) return '0'
  if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M'
  if (n >= 1000) return (n / 1000).toFixed(1) + 'K'
  return String(n)
}

function formatDuration(ms: number): string {
  if (!ms) return '—'
  if (ms < 1000) return ms + 'ms'
  return (ms / 1000).toFixed(1) + 's'
}

function fmtTime(iso: string) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

watch(statusFilter, () => {
  page.value = 1
  load()
})

onMounted(load)
</script>