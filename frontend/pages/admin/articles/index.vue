<template>
  <div class="space-y-6">
    <!-- Toolbar -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-4 flex flex-wrap items-center gap-3">
      <select v-model="filters.status" class="bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors">
        <option value="">All Statuses</option>
        <option value="published">Published</option>
        <option value="draft">Draft</option>
        <option value="archived">Archived</option>
      </select>
      <select v-model="filters.category" class="bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors">
        <option value="">All Categories</option>
        <option v-for="cat in categories" :key="cat.slug" :value="cat.slug">{{ cat.name }}</option>
      </select>
      <div class="flex-1 min-w-[200px]">
        <input v-model="filters.search" type="search" placeholder="Search articles..." class="w-full bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors" />
      </div>
      <select v-model="filters.perPage" class="bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors">
        <option :value="20">20</option>
        <option :value="50">50</option>
        <option :value="100">100</option>
      </select>
      <span class="text-xs text-admin-muted font-medium">{{ articles.total || 0 }} articles</span>
    </div>

    <!-- Batch action bar -->
    <div v-if="selectedIds.length > 0" class="bg-blue-50 rounded-[14px] border border-blue-200 p-4 flex items-center gap-3 animate-in fade-in slide-in-from-top-2">
      <span class="text-sm font-semibold text-blue-700 uppercase tracking-wider">{{ selectedIds.length }} selected</span>
      <button @click="batchAction('publish')" class="px-3 py-1.5 text-[11px] bg-emerald-600 hover:bg-emerald-700 text-white rounded-[14px] font-medium transition-colors uppercase tracking-wider">Publish</button>
      <button @click="batchAction('draft')" class="px-3 py-1.5 text-[11px] bg-amber-600 hover:bg-amber-700 text-white rounded-[14px] font-medium transition-colors uppercase tracking-wider">Set Draft</button>
      <button @click="batchAction('archive')" class="px-3 py-1.5 text-[11px] bg-slate-600 hover:bg-slate-700 text-white rounded-[14px] font-medium transition-colors uppercase tracking-wider">Archive</button>
      <button @click="batchAction('regenerate')" class="px-3 py-1.5 text-[11px] bg-admin-sidebar hover:bg-slate-800 text-white rounded-[14px] font-medium transition-colors uppercase tracking-wider">Regenerate</button>
      <button @click="batchAction('delete')" class="px-3 py-1.5 text-[11px] bg-red-600 hover:bg-red-700 text-white rounded-[14px] font-medium transition-colors uppercase tracking-wider">Delete</button>
      <button @click="clearSelection" class="ml-auto text-[11px] text-admin-muted hover:text-admin-text font-medium uppercase tracking-wider">Clear</button>
    </div>

    <!-- Table -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50/80 border-b border-admin-border">
            <th class="px-6 py-3.5 w-10"><input type="checkbox" :checked="allSelected" @change="toggleAll" class="rounded border-slate-400 text-admin-text focus:ring-slate-400 bg-slate-50" /></th>
            <th @click="toggleSort('title')" class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider cursor-pointer select-none hover:text-admin-text transition-colors">
              Title
              <span v-if="filters.sortBy === 'title'" class="ml-1">{{ filters.sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Category</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Status</th>
            <th @click="toggleSort('word_count')" class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider cursor-pointer select-none hover:text-admin-text transition-colors">
              Words
              <span v-if="filters.sortBy === 'word_count'" class="ml-1">{{ filters.sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Model</th>
            <th @click="toggleSort('generation_duration_seconds')" class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider cursor-pointer select-none hover:text-admin-text transition-colors">
              Gen Time
              <span v-if="filters.sortBy === 'generation_duration_seconds'" class="ml-1">{{ filters.sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th @click="toggleSort('views')" class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider cursor-pointer select-none hover:text-admin-text transition-colors">
              Views
              <span v-if="filters.sortBy === 'views'" class="ml-1">{{ filters.sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th @click="toggleSort('created_at')" class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider cursor-pointer select-none hover:text-admin-text transition-colors">
              Date
              <span v-if="filters.sortBy === 'created_at'" class="ml-1">{{ filters.sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="px-6 py-3.5 text-right text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="a in articles.data" :key="a.id" class="hover:bg-slate-50 transition-colors" :class="{ 'bg-amber-50/40': selectedIds.includes(a.id) }">
            <td class="px-6 py-3.5"><input type="checkbox" :checked="selectedIds.includes(a.id)" @change="toggleOne(a.id)" class="rounded border-slate-400 text-admin-text focus:ring-slate-400 bg-slate-50" /></td>
            <td class="px-6 py-3.5">
              <NuxtLink :to="`/admin/articles/${a.id}`" class="font-medium text-admin-text hover:text-slate-700 transition-colors line-clamp-1">{{ a.title }}</NuxtLink>
            </td>
            <td class="px-6 py-3.5 text-admin-muted whitespace-nowrap">{{ a.category || '—' }}</td>
            <td class="px-6 py-3.5"><span class="text-[11px] font-medium px-2.5 py-1 rounded-full" :class="statusClass(a.status)">{{ a.status }}</span></td>
            <td class="px-6 py-3.5 text-admin-muted text-right">{{ a.word_count || 0 }}</td>
            <td class="px-6 py-3.5 text-xs text-admin-muted whitespace-nowrap">{{ a.model || '—' }}</td>
            <td class="px-6 py-3.5 text-xs text-admin-muted">{{ fmtDuration(a.generation_duration_seconds) }}</td>
            <td class="px-6 py-3.5 text-admin-muted text-right">{{ a.views }}</td>
            <td class="px-6 py-3.5 text-xs text-admin-muted whitespace-nowrap">{{ formatDate(a.created_at) }}</td>
            <td class="px-6 py-3.5 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click="regenerateArticle(a.id)" class="p-1.5 text-admin-muted hover:text-admin-text hover:bg-slate-100 rounded-lg transition-colors" title="Regenerate"><RefreshCw class="w-3.5 h-3.5" /></button>
                <button @click="deleteArticle(a.id)" class="p-1.5 text-admin-muted hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete"><Trash2 class="w-3.5 h-3.5" /></button>
              </div>
            </td>
          </tr>
          <tr v-if="!articles.data?.length"><td colspan="10" class="px-6 py-12 text-center text-admin-muted">
            <FileText class="w-8 h-8 mx-auto mb-3 text-slate-400" />
            No articles found
          </td></tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="articles.total > 0" class="flex items-center justify-between">
      <span class="text-xs text-admin-muted">Showing {{ articles.from || 0 }}–{{ articles.to || 0 }} of {{ articles.total }}</span>
      <div class="flex gap-1">
        <button v-if="articles.current_page > 1" @click="filters.page = articles.current_page - 1" class="px-3 py-1.5 text-[11px] border border-admin-border rounded-[14px] hover:bg-slate-50 text-slate-600 transition-colors uppercase tracking-wider">Previous</button>
        <template v-for="p in pageNumbers" :key="p">
          <button v-if="p === '...'" class="px-3 py-1.5 text-[11px] text-slate-400" disabled>...</button>
          <button v-else @click="filters.page = p" class="px-3 py-1.5 text-[11px] border rounded-[14px] transition-colors" :class="p === articles.current_page ? 'bg-admin-sidebar text-white border-slate-900' : 'border-admin-border hover:bg-slate-50 text-slate-600'">{{ p }}</button>
        </template>
        <button v-if="articles.current_page < articles.last_page" @click="filters.page = articles.current_page + 1" class="px-3 py-1.5 text-[11px] border border-admin-border rounded-[14px] hover:bg-slate-50 text-slate-600 transition-colors uppercase tracking-wider">Next</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { RefreshCw, Trash2, FileText } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const articles = ref<any>({ data: [], total: 0 })
const categories = ref<any[]>([])
const selectedIds = ref<number[]>([])

const filters = reactive({
  status: '',
  category: '',
  search: '',
  page: 1,
  perPage: 20,
  sortBy: 'created_at',
  sortDir: 'desc' as 'asc' | 'desc',
})

const allSelected = computed(() => {
  const data = articles.value.data || []
  return data.length > 0 && data.every((a: any) => selectedIds.value.includes(a.id))
})

const pageNumbers = computed(() => {
  const current = articles.value.current_page || 1
  const last = articles.value.last_page || 1
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

watch(() => [filters.status, filters.category, filters.search, filters.perPage, filters.sortBy, filters.sortDir], () => {
  filters.page = 1
  loadArticles()
}, { deep: true })

watch(() => filters.page, () => {
  loadArticles()
})

function statusClass(status: string) {
  if (status === 'published') return 'bg-emerald-50 text-emerald-700 border border-emerald-100'
  if (status === 'draft') return 'bg-amber-50 text-amber-700 border border-amber-100'
  return 'bg-slate-100 text-slate-600 border border-admin-border'
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function fmtDuration(seconds: number) {
  if (!seconds) return '—'
  if (seconds < 60) return `${seconds}s`
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}m ${s}s`
}

function toggleSort(field: string) {
  if (filters.sortBy === field) {
    filters.sortDir = filters.sortDir === 'asc' ? 'desc' : 'asc'
  } else {
    filters.sortBy = field
    filters.sortDir = 'desc'
  }
}

function toggleAll() {
  const data = articles.value.data || []
  if (allSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = data.map((a: any) => a.id)
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

async function loadArticles() {
  try {
    const params: Record<string, string | number> = {
      page: filters.page,
      per_page: filters.perPage,
      sort_by: filters.sortBy,
      sort_dir: filters.sortDir,
    }
    if (filters.status) params.status = filters.status
    if (filters.category) params.category = filters.category
    if (filters.search) params.search = filters.search
    articles.value = await api.getArticles(params)
  } catch {}
}

async function batchAction(action: string) {
  const label = { delete: 'Delete', regenerate: 'Regenerate', publish: 'Publish', draft: 'Set draft', archive: 'Archive' }[action] || action
  if (action === 'delete' && !confirm(`Permanently delete ${selectedIds.value.length} articles?`)) return
  if (action === 'regenerate' && !confirm(`Regenerate ${selectedIds.value.length} articles? Existing articles will be replaced.`)) return
  try {
    await api.batchArticles(selectedIds.value, action)
    selectedIds.value = []
    await loadArticles()
  } catch {}
}

async function regenerateArticle(id: number) {
  if (!confirm('Regenerate this article?')) return
  try {
    await api.regenerateArticle(id)
    await loadArticles()
  } catch {}
}

async function deleteArticle(id: number) {
  if (!confirm('Delete this article permanently?')) return
  try {
    await api.deleteArticle(id)
    await loadArticles()
  } catch {}
}

onMounted(async () => {
  loadArticles()
  try {
    const catRes = await api.getCategories()
    categories.value = catRes.data?.flatMap((c: any) => [c, ...(c.children || [])]) || []
  } catch {}
})
</script>
