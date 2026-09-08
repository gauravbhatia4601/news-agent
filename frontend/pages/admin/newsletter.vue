<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-admin-sidebar/5 flex items-center justify-center"><Mail class="w-5 h-5 text-admin-accent" /></div>
        <div>
          <h1 class="text-[18px] font-semibold text-admin-text">Newsletter</h1>
          <p class="text-xs text-admin-muted">Manage email subscribers</p>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center"><Users class="w-4 h-4 text-emerald-600" /></div>
        </div>
        <p class="text-2xl font-bold text-admin-text tracking-tight">{{ stats.active ?? 0 }}</p>
        <p class="text-xs text-admin-muted mt-1">Active Subscribers</p>
      </div>
      <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center"><UserMinus class="w-4 h-4 text-admin-muted" /></div>
        </div>
        <p class="text-2xl font-bold text-admin-text tracking-tight">{{ stats.unsubscribed ?? 0 }}</p>
        <p class="text-xs text-admin-muted mt-1">Unsubscribed</p>
      </div>
      <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center"><BarChart3 class="w-4 h-4 text-admin-accent" /></div>
        </div>
        <p class="text-2xl font-bold text-admin-text tracking-tight">{{ stats.total ?? 0 }}</p>
        <p class="text-xs text-admin-muted mt-1">Total All-time</p>
      </div>
    </div>

    <!-- Filters + Table -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] overflow-hidden">
      <div class="px-6 py-4 border-b border-admin-border flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-2">
          <button
            v-for="opt in statusOptions"
            :key="opt.value"
            @click="statusFilter = opt.value"
            class="px-3 py-1.5 rounded-[10px] text-xs font-medium transition-all"
            :class="statusFilter === opt.value ? 'bg-admin-sidebar text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-admin-border'"
          >{{ opt.label }}</button>
        </div>
        <div class="flex items-center gap-2">
          <div class="relative">
            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search email..."
              class="h-9 pl-9 pr-4 bg-slate-50 border border-admin-border rounded-[10px] text-sm text-admin-text focus-ring transition-colors w-56"
              @input="onSearchInput"
            />
          </div>
          <button @click="exportCsv" class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-[10px] border border-admin-border transition-colors inline-flex items-center gap-1.5"
          >
            <Download class="w-3.5 h-3.5" /> Export CSV
          </button>
        </div>
      </div>

      <div v-if="loading" class="p-12 text-center">
        <Loader2 class="w-8 h-8 text-slate-400 animate-spin mx-auto mb-3" />
        <p class="text-sm text-admin-muted">Loading subscribers...</p>
      </div>
      <div v-else-if="!subscribers.length" class="p-12 text-center">
        <Mail class="w-8 h-8 text-slate-300 mx-auto mb-3" />
        <p class="text-sm text-admin-muted">No subscribers found</p>
      </div>
      <div v-else>
        <div class="divide-y divide-slate-100">
          <div v-for="sub in subscribers" :key="sub.id" class="px-6 py-3.5 flex items-center gap-4 hover:bg-slate-50 transition-colors">
            <div class="w-9 h-9 rounded-full bg-slate-100 border border-admin-border flex items-center justify-center text-[11px] font-bold text-slate-600 shrink-0">{{ initials(sub.email) }}</div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-admin-text truncate">{{ sub.email }}</p>
              <p class="text-xs text-slate-400">Subscribed {{ fmtTime(sub.subscribed_at) }}{{ sub.source ? ' · ' + sub.source : '' }}</p>
            </div>
            <span v-if="sub.unsubscribed_at" class="px-2.5 py-1 text-[11px] font-medium rounded-full bg-slate-100 text-admin-muted">Unsubscribed</span>
            <span v-else class="px-2.5 py-1 text-[11px] font-medium rounded-full bg-emerald-50 text-emerald-600">Active</span>
            <button @click="removeSubscriber(sub.id)" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Remove"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-admin-border flex items-center justify-between">
          <p class="text-xs text-slate-400">{{ total }} subscriber{{ total === 1 ? '' : 's' }}</p>
          <div class="flex items-center gap-2">
            <button @click="goPage(page - 1)" :disabled="page <= 1" class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg border border-admin-border disabled:opacity-40 transition-colors">Prev</button>
            <span class="text-xs text-admin-muted">{{ page }} / {{ lastPage }}</span>
            <button @click="goPage(page + 1)" :disabled="page >= lastPage" class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg border border-admin-border disabled:opacity-40 transition-colors">Next</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Loader2, Mail, Users, UserMinus, BarChart3, Search, Download, Trash2 } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const subscribers = ref<any[]>([])
const stats = ref<any>({})
const loading = ref(true)
const statusFilter = ref<'active' | 'unsubscribed' | 'all'>('active')
const searchQuery = ref('')
const page = ref(1)
const total = ref(0)
const lastPage = ref(1)

const statusOptions = [
  { value: 'active' as const, label: 'Active' },
  { value: 'unsubscribed' as const, label: 'Unsubscribed' },
  { value: 'all' as const, label: 'All' },
]

let searchTimeout: ReturnType<typeof setTimeout> | null = null

async function load() {
  loading.value = true
  try {
    const res = await api.getSubscribers({
      status: statusFilter.value,
      page: page.value,
      per_page: 25,
      search: searchQuery.value || undefined,
    })
    subscribers.value = res.data.subscribers
    stats.value = res.data.stats
    total.value = res.data.total
    lastPage.value = res.data.last_page
  } catch {}
  loading.value = false
}

function onSearchInput() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
}

function goPage(p: number) {
  page.value = Math.max(1, Math.min(p, lastPage.value))
  load()
}

async function removeSubscriber(id: number) {
  if (!confirm('Remove this subscriber permanently?')) return
  try {
    await api.deleteSubscriber(id)
    await load()
  } catch {}
}

function exportCsv() {
  const headers = ['email', 'source', 'subscribed_at', 'unsubscribed_at', 'status']
  const rows = subscribers.value.map((s: any) => [
    s.email,
    s.source ?? '',
    s.subscribed_at ?? '',
    s.unsubscribed_at ?? '',
    s.unsubscribed_at ? 'unsubscribed' : 'active',
  ])
  const csv = [headers.join(','), ...rows.map((r: any[]) => r.map((c: any) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(','))].join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `newsletter-subscribers-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

function initials(email: string) {
  return (email?.[0] || '?').toUpperCase()
}

function fmtTime(iso: string) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

watch(statusFilter, () => {
  page.value = 1
  load()
})

onMounted(load)
</script>