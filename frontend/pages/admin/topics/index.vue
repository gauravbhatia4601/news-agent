<template>
  <div class="space-y-6">
    <!-- Toolbar -->
    <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-4 flex flex-wrap items-center gap-3">
      <select v-model="filters.status" class="bg-[#0a0e1a] border border-white/[0.08] rounded-[14px] px-4 py-2.5 text-sm text-white focus:outline-none focus:border-[#f5a623]/40 transition-colors">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="generated">Generated</option>
        <option value="failed">Failed</option>
      </select>
      <select v-model="filters.category" class="bg-[#0a0e1a] border border-white/[0.08] rounded-[14px] px-4 py-2.5 text-sm text-white focus:outline-none focus:border-[#f5a623]/40 transition-colors">
        <option value="">All Categories</option>
        <option v-for="cat in categories" :key="cat.slug" :value="cat.slug">{{ cat.name }}</option>
      </select>
      <input v-model="filters.search" type="search" placeholder="Search topics..."
        class="bg-[#0a0e1a] border border-white/[0.08] rounded-[14px] px-4 py-2.5 text-sm text-white focus:outline-none focus:border-[#f5a623]/40 transition-colors flex-1 min-w-[200px]" />
      <select v-model="filters.perPage" class="bg-[#0a0e1a] border border-white/[0.08] rounded-[14px] px-4 py-2.5 text-sm text-white focus:outline-none focus:border-[#f5a623]/40 transition-colors">
        <option :value="20">20</option>
        <option :value="50">50</option>
        <option :value="100">100</option>
      </select>
      <span class="text-xs text-[#64748B] font-medium font-mono">{{ topics.total || 0 }} topics</span>
    </div>

    <!-- Batch action bar -->
    <div v-if="selectedIds.length > 0" class="bg-blue-500/10 rounded-[14px] border border-blue-500/20 p-4 flex items-center gap-3">
      <span class="text-sm font-semibold text-blue-400 font-mono uppercase tracking-wider">{{ selectedIds.length }} selected</span>
      <button @click="batchAction('dispatch')" class="px-3 py-1.5 text-[11px] bg-emerald-600 hover:bg-emerald-700 text-white rounded-[14px] font-medium transition-colors font-mono uppercase tracking-wider">Dispatch to Queue</button>
      <button @click="batchAction('retry')" class="px-3 py-1.5 text-[11px] bg-amber-600 hover:bg-amber-700 text-white rounded-[14px] font-medium transition-colors font-mono uppercase tracking-wider">Retry Failed</button>
      <button @click="batchAction('delete')" class="px-3 py-1.5 text-[11px] bg-red-600 hover:bg-red-700 text-white rounded-[14px] font-medium transition-colors font-mono uppercase tracking-wider">Delete</button>
      <button @click="clearSelection" class="ml-auto text-[11px] text-[#94A3B8] hover:text-white font-medium font-mono uppercase tracking-wider">Clear</button>
    </div>

    <!-- Table -->
    <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-white/[0.04] border-b border-white/[0.06]">
            <th class="px-6 py-3.5 w-10"><input type="checkbox" :checked="allSelected" @change="toggleAll" class="rounded border-[#475569] text-[#1a2233] focus:ring-[#f5a623]/40 bg-[#0a0e1a]" /></th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">Topic</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">Category</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">Status</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">Sources</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">Retries</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">Article</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">Updated</th>
            <th class="px-6 py-3.5 text-right text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/[0.04]">
          <tr v-for="t in topics.data" :key="t.id" class="hover:bg-white/[0.02] transition-colors" :class="{ 'bg-[#f5a623]/5': selectedIds.includes(t.id) }">
            <td class="px-6 py-3.5"><input type="checkbox" :checked="selectedIds.includes(t.id)" @change="toggleOne(t.id)" class="rounded border-[#475569] text-[#1a2233] focus:ring-[#f5a623]/40 bg-[#0a0e1a]" /></td>
            <td class="px-6 py-3.5">
              <NuxtLink :to="`/admin/topics/${t.id}`" class="font-medium text-white hover:text-[#f5a623] transition-colors line-clamp-1">{{ t.topic_name }}</NuxtLink>
            </td>
            <td class="px-6 py-3.5 text-[#94A3B8] whitespace-nowrap">{{ t.category || '—' }}</td>
            <td class="px-6 py-3.5"><span class="text-[11px] font-medium px-2.5 py-1 rounded-full" :class="topicStatusClass(t.generation_status)">{{ t.generation_status }}</span></td>
            <td class="px-6 py-3.5 text-[#94A3B8] text-center">{{ t.source_count }}</td>
            <td class="px-6 py-3.5 text-[#94A3B8] text-center">{{ t.retry_count }}</td>
            <td class="px-6 py-3.5">
              <span v-if="t.has_article" class="text-[11px] font-medium px-2.5 py-1 rounded-full" :class="statusClass(t.article_status)">{{ t.article_status }}</span>
              <span v-else class="text-xs text-[#64748B]">—</span>
            </td>
            <td class="px-6 py-3.5 text-xs text-[#64748B] whitespace-nowrap font-mono">{{ formatDate(t.updated_at) }}</td>
            <td class="px-6 py-3.5 text-right">
              <div class="flex items-center justify-end gap-1">
                <button v-if="t.generation_status === 'pending'" @click="dispatchTopic(t.id)" class="p-1.5 text-[#94A3B8] hover:text-emerald-400 hover:bg-emerald-500/10 rounded-lg transition-colors" title="Dispatch to queue"><Send class="w-3.5 h-3.5" /></button>
                <button v-if="t.generation_status === 'failed'" @click="retryTopic(t.id)" class="p-1.5 text-[#94A3B8] hover:text-amber-400 hover:bg-amber-400/10 rounded-lg transition-colors" title="Retry"><RefreshCw class="w-3.5 h-3.5" /></button>
                <button @click="deleteTopic(t.id)" class="p-1.5 text-[#94A3B8] hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors" title="Delete"><Trash2 class="w-3.5 h-3.5" /></button>
              </div>
            </td>
          </tr>
          <tr v-if="!topics.data?.length"><td colspan="9" class="px-6 py-12 text-center text-[#94A3B8]">
            <FolderOpen class="w-8 h-8 mx-auto mb-3 text-[#475569]" />
            No topics found
          </td></tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="topics.total > 0" class="flex items-center justify-between">
      <span class="text-xs text-[#64748B] font-mono">Showing {{ topics.from || 0 }}–{{ topics.to || 0 }} of {{ topics.total }}</span>
      <div class="flex gap-1">
        <button v-if="topics.current_page > 1" @click="filters.page = topics.current_page - 1" class="px-3 py-1.5 text-[11px] border border-white/[0.08] rounded-[14px] hover:bg-white/[0.04] text-[#94A3B8] transition-colors font-mono uppercase tracking-wider">Previous</button>
        <template v-for="p in pageNumbers" :key="p">
          <button v-if="p === '...'" class="px-3 py-1.5 text-[11px] text-[#64748B]" disabled>...</button>
          <button v-else @click="filters.page = p" class="px-3 py-1.5 text-[11px] border rounded-[14px] transition-colors" :class="p === topics.current_page ? 'bg-[#1a2233] text-white border-[#1a2233]' : 'border-white/[0.08] hover:bg-white/[0.04] text-[#94A3B8]'">{{ p }}</button>
        </template>
        <button v-if="topics.current_page < topics.last_page" @click="filters.page = topics.current_page + 1" class="px-3 py-1.5 text-[11px] border border-white/[0.08] rounded-[14px] hover:bg-white/[0.04] text-[#94A3B8] transition-colors font-mono uppercase tracking-wider">Next</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { RefreshCw, Send, Trash2, FolderOpen } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const topics = ref<any>({ data: [], total: 0 })
const categories = ref<any[]>([])
const selectedIds = ref<number[]>([])

const filters = reactive({
  status: '',
  category: '',
  search: '',
  page: 1,
  perPage: 20,
})

const allSelected = computed(() => {
  const data = topics.value.data || []
  return data.length > 0 && data.every((t: any) => selectedIds.value.includes(t.id))
})

const pageNumbers = computed(() => {
  const current = topics.value.current_page || 1
  const last = topics.value.last_page || 1
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)
  const pages: (number | string)[] = [1]
  if (current > 3) pages.push('...')
  const start = Math.max(2, current - 1)
  const end = Math.min(last - 1, current + 1)
  for (let i = start; i <= end; i++) pages.push(i)
  if (current < last - 2) pages.push('...')
  pages.push(last)
  return pages
})

watch(() => [filters.status, filters.category, filters.search, filters.perPage], () => {
  filters.page = 1
  loadTopics()
}, { deep: true })

watch(() => filters.page, () => {
  loadTopics()
})

function topicStatusClass(s: string) {
  if (s === 'generated') return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
  if (s === 'pending') return 'bg-blue-500/10 text-blue-400 border border-blue-500/20'
  if (s === 'failed') return 'bg-red-500/10 text-red-400 border border-red-500/20'
  return 'bg-white/[0.06] text-[#94A3B8] border border-white/[0.08]'
}

function statusClass(s: string) {
  if (s === 'published') return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
  if (s === 'draft') return 'bg-amber-400/10 text-amber-400 border border-amber-400/20'
  return 'bg-white/[0.06] text-[#94A3B8] border border-white/[0.08]'
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function toggleAll() {
  const data = topics.value.data || []
  if (allSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = data.map((t: any) => t.id)
  }
}

function toggleOne(id: number) {
  const idx = selectedIds.value.indexOf(id)
  if (idx >= 0) {
    selectedIds.value.splice(idx, 1)
  } else {
    selectedIds.value.push(id)
  }
}

function clearSelection() {
  selectedIds.value = []
}

async function loadTopics() {
  try {
    const params: Record<string, string | number> = {
      page: filters.page,
      per_page: filters.perPage,
    }
    if (filters.status) params.status = filters.status
    if (filters.category) params.category = filters.category
    if (filters.search) params.search = filters.search
    topics.value = await api.getTopics(params)
  } catch {}
}

async function batchAction(action: string) {
  const label = { delete: 'Delete', dispatch: 'Dispatch', retry: 'Retry' }[action] || action
  if (action === 'delete' && !confirm(`Permanently delete ${selectedIds.value.length} topics and their articles?`)) return
  try {
    await api.batchTopics(selectedIds.value, action)
    selectedIds.value = []
    await loadTopics()
  } catch {}
}

async function dispatchTopic(id: number) {
  try {
    await api.dispatchTopic(id)
    await loadTopics()
  } catch {}
}

async function retryTopic(id: number) {
  try {
    await api.retryTopic(id)
    await loadTopics()
  } catch {}
}

async function deleteTopic(id: number) {
  if (!confirm('Delete this topic and its article?')) return
  try {
    await api.deleteTopic(id)
    await loadTopics()
  } catch {}
}

onMounted(async () => {
  loadTopics()
  try {
    const catRes = await api.getCategories()
    categories.value = catRes.data?.flatMap((c: any) => [c, ...(c.children || [])]) || []
  } catch {}
})
</script>
