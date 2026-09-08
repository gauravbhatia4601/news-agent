<template>
  <div class="space-y-6">
    <!-- Toolbar -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-4 flex flex-wrap items-center gap-3">
      <select v-model="filters.status" class="bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="generated">Generated</option>
        <option value="failed">Failed</option>
      </select>
      <select v-model="filters.category" class="bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors">
        <option value="">All Categories</option>
        <option v-for="cat in categories" :key="cat.slug" :value="cat.slug">{{ cat.name }}</option>
      </select>
      <input v-model="filters.search" type="search" placeholder="Search topics..."
        class="bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors flex-1 min-w-[200px]" />
      <select v-model="filters.perPage" class="bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors">
        <option :value="20">20</option>
        <option :value="50">50</option>
        <option :value="100">100</option>
      </select>
      <span class="text-xs text-admin-muted font-medium">{{ topics.total || 0 }} topics</span>
    </div>

    <!-- Batch action bar -->
    <div v-if="selectedIds.length > 0" class="bg-blue-50 rounded-[14px] border border-blue-200 p-4 flex items-center gap-3">
      <span class="text-sm font-semibold text-blue-700 uppercase tracking-wider">{{ selectedIds.length }} selected</span>
      <button @click="batchAction('dispatch')" class="px-3 py-1.5 text-[11px] bg-emerald-600 hover:bg-emerald-700 text-white rounded-[14px] font-medium transition-colors uppercase tracking-wider">Dispatch to Queue</button>
      <button @click="batchAction('retry')" class="px-3 py-1.5 text-[11px] bg-amber-600 hover:bg-amber-700 text-white rounded-[14px] font-medium transition-colors uppercase tracking-wider">Retry Failed</button>
      <button @click="batchAction('delete')" class="px-3 py-1.5 text-[11px] bg-red-600 hover:bg-red-700 text-white rounded-[14px] font-medium transition-colors uppercase tracking-wider">Delete</button>
      <button @click="clearSelection" class="ml-auto text-[11px] text-admin-muted hover:text-admin-text font-medium uppercase tracking-wider">Clear</button>
    </div>

    <!-- Table -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50/80 border-b border-admin-border">
            <th class="px-6 py-3.5 w-10"><input type="checkbox" :checked="allSelected" @change="toggleAll" class="rounded border-slate-400 text-admin-text focus:ring-slate-400 bg-slate-50" /></th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Topic</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Category</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Status</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Sources</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Retries</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Article</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Updated</th>
            <th class="px-6 py-3.5 text-right text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="t in topics.data" :key="t.id" class="hover:bg-slate-50 transition-colors" :class="{ 'bg-amber-50/40': selectedIds.includes(t.id) }">
            <td class="px-6 py-3.5"><input type="checkbox" :checked="selectedIds.includes(t.id)" @change="toggleOne(t.id)" class="rounded border-slate-400 text-admin-text focus:ring-slate-400 bg-slate-50" /></td>
            <td class="px-6 py-3.5">
              <NuxtLink :to="`/admin/topics/${t.id}`" class="font-medium text-admin-text hover:text-slate-700 transition-colors line-clamp-1">{{ t.topic_name }}</NuxtLink>
            </td>
            <td class="px-6 py-3.5 text-admin-muted whitespace-nowrap">{{ t.category || '—' }}</td>
            <td class="px-6 py-3.5"><span class="text-[11px] font-medium px-2.5 py-1 rounded-full" :class="topicStatusClass(t.generation_status)">{{ t.generation_status }}</span></td>
            <td class="px-6 py-3.5 text-admin-muted text-center">{{ t.source_count }}</td>
            <td class="px-6 py-3.5 text-admin-muted text-center">{{ t.retry_count }}</td>
            <td class="px-6 py-3.5">
              <span v-if="t.has_article" class="text-[11px] font-medium px-2.5 py-1 rounded-full" :class="statusClass(t.article_status)">{{ t.article_status }}</span>
              <span v-else class="text-xs text-slate-400">—</span>
            </td>
            <td class="px-6 py-3.5 text-xs text-admin-muted whitespace-nowrap">{{ formatDate(t.updated_at) }}</td>
            <td class="px-6 py-3.5 text-right">
              <div class="flex items-center justify-end gap-1">
                <button v-if="t.generation_status === 'pending'" @click="dispatchTopic(t.id)" class="p-1.5 text-admin-muted hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="Dispatch to queue"><Send class="w-3.5 h-3.5" /></button>
                <button v-if="t.generation_status === 'failed'" @click="retryTopic(t.id)" class="p-1.5 text-admin-muted hover:text-admin-accent hover:bg-amber-50 rounded-lg transition-colors" title="Retry"><RefreshCw class="w-3.5 h-3.5" /></button>
                <button @click="deleteTopic(t.id)" class="p-1.5 text-admin-muted hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete"><Trash2 class="w-3.5 h-3.5" /></button>
              </div>
            </td>
          </tr>
          <tr v-if="!topics.data?.length"><td colspan="9" class="px-6 py-12 text-center text-admin-muted">
            <FolderOpen class="w-8 h-8 mx-auto mb-3 text-slate-400" />
            No topics found
          </td></tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="topics.total > 0" class="flex items-center justify-between">
      <span class="text-xs text-admin-muted">Showing {{ topics.from || 0 }}–{{ topics.to || 0 }} of {{ topics.total }}</span>
      <div class="flex gap-1">
        <button v-if="topics.current_page > 1" @click="filters.page = topics.current_page - 1" class="px-3 py-1.5 text-[11px] border border-admin-border rounded-[14px] hover:bg-slate-50 text-slate-600 transition-colors uppercase tracking-wider">Previous</button>
        <template v-for="p in pageNumbers" :key="p">
          <button v-if="p === '...'" class="px-3 py-1.5 text-[11px] text-slate-400" disabled>...</button>
          <button v-else @click="filters.page = p" class="px-3 py-1.5 text-[11px] border rounded-[14px] transition-colors" :class="p === topics.current_page ? 'bg-admin-sidebar text-white border-slate-900' : 'border-admin-border hover:bg-slate-50 text-slate-600'">{{ p }}</button>
        </template>
        <button v-if="topics.current_page < topics.last_page" @click="filters.page = topics.current_page + 1" class="px-3 py-1.5 text-[11px] border border-admin-border rounded-[14px] hover:bg-slate-50 text-slate-600 transition-colors uppercase tracking-wider">Next</button>
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
  if (s === 'generated') return 'bg-emerald-50 text-emerald-700 border border-emerald-100'
  if (s === 'pending') return 'bg-blue-50 text-blue-700 border border-blue-100'
  if (s === 'failed') return 'bg-red-50 text-red-700 border border-red-100'
  return 'bg-slate-100 text-slate-600 border border-admin-border'
}

function statusClass(s: string) {
  if (s === 'published') return 'bg-emerald-50 text-emerald-700 border border-emerald-100'
  if (s === 'draft') return 'bg-amber-50 text-amber-700 border border-amber-100'
  return 'bg-slate-100 text-slate-600 border border-admin-border'
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
