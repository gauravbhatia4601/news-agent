<script setup lang="ts">
import { Radio, Trash2, RefreshCw, Play, Square, Zap, Plus, X, FolderOpen } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const stories = ref<any>({ data: [], total: 0 })

const filters = reactive({
  urgency: '',
  status: '',
  search: '',
  page: 1,
  perPage: 20,
})

// Create / edit modal state
const showModal = ref(false)
const editingId = ref<number | null>(null)
const form = reactive({
  title: '',
  search_query: '',
  urgency: 'developing',
  description: '',
})

const pageNumbers = computed(() => {
  const current = stories.value.current_page || 1
  const last = stories.value.last_page || 1
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

watch(() => [filters.urgency, filters.status, filters.search, filters.perPage], () => {
  filters.page = 1
  loadStories()
}, { deep: true })

watch(() => filters.page, () => loadStories())

async function loadStories() {
  try {
    const params: Record<string, string | number> = {
      page: filters.page,
      per_page: filters.perPage,
    }
    if (filters.urgency) params.urgency = filters.urgency
    if (filters.status) params.status = filters.status
    if (filters.search) params.search = filters.search
    stories.value = await api.getStories(params)
  } catch {}
}

function openCreate() {
  editingId.value = null
  form.title = ''
  form.search_query = ''
  form.urgency = 'developing'
  form.description = ''
  showModal.value = true
}

function openEdit(s: any) {
  editingId.value = s.id
  form.title = s.title
  form.search_query = s.search_query
  form.urgency = s.urgency
  form.description = s.description ?? ''
  showModal.value = true
}

async function saveStory() {
  try {
    if (editingId.value) {
      await api.updateStory(editingId.value, {
        title: form.title,
        search_query: form.search_query,
        urgency: form.urgency,
        description: form.description || null,
      })
    } else {
      await api.createStory({
        title: form.title,
        search_query: form.search_query,
        urgency: form.urgency,
        description: form.description || null,
      })
    }
    showModal.value = false
    await loadStories()
  } catch {}
}

async function activateStory(id: number) {
  try {
    await api.activateStory(id)
    await loadStories()
  } catch {}
}

async function concludeStory(id: number) {
  try {
    await api.concludeStory(id)
    await loadStories()
  } catch {}
}

async function triggerMonitor(id: number) {
  try {
    const res = await api.triggerStoryMonitor(id)
    const d = res.data
    alert(`Monitor cycle complete: ${d.discovered} discovered, ${d.judged_new} new, ${d.dispatched} dispatched, concluded: ${d.concluded}`)
    await loadStories()
  } catch {}
}

async function deleteStory(id: number) {
  if (!confirm('Delete this story? Linked articles will be unlinked but kept.')) return
  try {
    await api.deleteStory(id)
    await loadStories()
  } catch {}
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

onMounted(() => loadStories())
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-admin-text">Live Stories</h1>
        <p class="text-sm text-admin-muted mt-1">Manage ongoing event timelines.</p>
      </div>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2.5 bg-admin-sidebar text-white rounded-[14px] text-sm font-medium hover:opacity-90 transition-opacity">
        <Plus class="w-4 h-4" />
        Create Story
      </button>
    </div>

    <!-- Toolbar -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-4 flex flex-wrap items-center gap-3">
      <select v-model="filters.urgency" class="bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors">
        <option value="">All Urgencies</option>
        <option value="live">Live</option>
        <option value="developing">Developing</option>
        <option value="ongoing">Ongoing</option>
        <option value="concluded">Concluded</option>
      </select>
      <select v-model="filters.status" class="bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors">
        <option value="">All Statuses</option>
        <option value="auto-detected">Auto-detected</option>
        <option value="admin-created">Admin-created</option>
      </select>
      <input v-model="filters.search" type="search" placeholder="Search stories..."
        class="bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors flex-1 min-w-[200px]" />
      <span class="text-xs text-admin-muted font-medium">{{ stories.total || 0 }} stories</span>
    </div>

    <!-- Table -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50/80 border-b border-admin-border">
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Title</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Search Query</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Urgency</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Status</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Updates</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Started</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Last Monitored</th>
            <th class="px-6 py-3.5 text-right text-[11px] font-semibold text-admin-muted uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="s in stories.data" :key="s.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-3.5 font-medium text-admin-text line-clamp-1 max-w-[240px]">{{ s.title }}</td>
            <td class="px-6 py-3.5 text-admin-muted whitespace-nowrap text-xs">{{ s.search_query }}</td>
            <td class="px-6 py-3.5"><NewsLiveBadge :urgency="s.urgency" /></td>
            <td class="px-6 py-3.5"><span class="text-[11px] font-medium px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 border border-admin-border">{{ s.status }}</span></td>
            <td class="px-6 py-3.5 text-admin-muted text-center">{{ s.update_count }}</td>
            <td class="px-6 py-3.5 text-xs text-admin-muted whitespace-nowrap">{{ formatDate(s.started_at) }}</td>
            <td class="px-6 py-3.5 text-xs text-admin-muted whitespace-nowrap">{{ formatDate(s.last_monitored_at) }}</td>
            <td class="px-6 py-3.5 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click="openEdit(s)" class="p-1.5 text-admin-muted hover:text-admin-accent hover:bg-amber-50 rounded-lg transition-colors" title="Edit">
                  <RefreshCw class="w-3.5 h-3.5" />
                </button>
                <button v-if="s.urgency === 'concluded'" @click="activateStory(s.id)" class="p-1.5 text-admin-muted hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="Activate">
                  <Play class="w-3.5 h-3.5" />
                </button>
                <button v-if="s.urgency !== 'concluded'" @click="concludeStory(s.id)" class="p-1.5 text-admin-muted hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Conclude">
                  <Square class="w-3.5 h-3.5" />
                </button>
                <button @click="triggerMonitor(s.id)" class="p-1.5 text-admin-muted hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Trigger Monitor">
                  <Zap class="w-3.5 h-3.5" />
                </button>
                <button @click="deleteStory(s.id)" class="p-1.5 text-admin-muted hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!stories.data?.length"><td colspan="8" class="px-6 py-12 text-center text-admin-muted">
            <FolderOpen class="w-8 h-8 mx-auto mb-3 text-slate-400" />
            No stories found
          </td></tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="stories.total > 0" class="flex items-center justify-between">
      <span class="text-xs text-admin-muted">Showing {{ stories.from || 0 }}–{{ stories.to || 0 }} of {{ stories.total }}</span>
      <div class="flex gap-1">
        <button v-if="stories.current_page > 1" @click="filters.page = stories.current_page - 1" class="px-3 py-1.5 text-[11px] border border-admin-border rounded-[14px] hover:bg-slate-50 text-slate-600 transition-colors uppercase tracking-wider">Previous</button>
        <template v-for="p in pageNumbers" :key="p">
          <button v-if="p === '...'" class="px-3 py-1.5 text-[11px] text-slate-400" disabled>...</button>
          <button v-else @click="filters.page = p" class="px-3 py-1.5 text-[11px] border rounded-[14px] transition-colors" :class="p === stories.current_page ? 'bg-admin-sidebar text-white border-slate-900' : 'border-admin-border hover:bg-slate-50 text-slate-600'">{{ p }}</button>
        </template>
        <button v-if="stories.current_page < stories.last_page" @click="filters.page = stories.current_page + 1" class="px-3 py-1.5 text-[11px] border border-admin-border rounded-[14px] hover:bg-slate-50 text-slate-600 transition-colors uppercase tracking-wider">Next</button>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/30" @click="showModal = false" />
      <div class="relative bg-admin-surface rounded-[14px] border border-admin-border shadow-xl w-full max-w-lg p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-admin-text">{{ editingId ? 'Edit Story' : 'Create Story' }}</h2>
          <button @click="showModal = false" class="text-admin-muted hover:text-admin-text transition-colors"><X class="w-5 h-5" /></button>
        </div>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-admin-muted uppercase tracking-wider mb-1.5">Title</label>
            <input v-model="form.title" type="text" class="w-full bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors" placeholder="Story title" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-admin-muted uppercase tracking-wider mb-1.5">Search Query</label>
            <input v-model="form.search_query" type="text" class="w-full bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors" placeholder="Query for monitoring" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-admin-muted uppercase tracking-wider mb-1.5">Urgency</label>
            <select v-model="form.urgency" class="w-full bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors">
              <option value="live">Live</option>
              <option value="developing">Developing</option>
              <option value="ongoing">Ongoing</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-admin-muted uppercase tracking-wider mb-1.5">Description</label>
            <textarea v-model="form.description" rows="3" class="w-full bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors" placeholder="Optional description"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button @click="showModal = false" class="px-4 py-2 text-sm text-admin-muted hover:text-admin-text transition-colors">Cancel</button>
          <button @click="saveStory" class="px-4 py-2 bg-admin-sidebar text-white rounded-[14px] text-sm font-medium hover:opacity-90 transition-opacity">
            {{ editingId ? 'Save' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>