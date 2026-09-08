<template>
  <div v-if="article" class="space-y-6">
    <NuxtLink to="/admin/articles" class="text-sm text-admin-muted hover:text-admin-text transition-colors">&larr; Back to Articles</NuxtLink>

    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <div class="flex items-start justify-between gap-4 mb-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-2xl font-bold text-admin-text">{{ article.title }}</h1>
          <div class="flex items-center gap-3 mt-2 text-sm text-admin-muted">
            <span>By {{ article.metadata.author }}</span>
            <span>&middot;</span>
            <span>{{ article.category || 'Uncategorized' }}</span>
            <span>&middot;</span>
            <span>{{ formatDate(article.created_at) }}</span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-1 rounded-full" :class="statusClass(article.status)">{{ article.status }}</span>
          <button @click="regenerate" :disabled="regenerating"
            class="text-[10px] font-semibold uppercase tracking-wider px-3 py-1 border border-admin-border hover:border-slate-400 rounded-full transition-colors disabled:opacity-50"
          >{{ regenerating ? 'Regenerating...' : 'Regenerate' }}</button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
          <h2 class="text-[15px] font-bold text-admin-text mb-4">Content</h2>
          <div class="prose prose-sm max-w-none text-slate-700" v-html="sanitizedContent" />
        </div>

        <div v-if="article.sources?.length" class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
          <h2 class="text-[15px] font-bold text-admin-text mb-4">Sources</h2>
          <div class="space-y-2">
            <div v-for="s in article.sources" :key="s.id" class="text-sm">
              <a :href="s.source_url" target="_blank" class="text-blue-600 hover:underline">{{ s.source_name }}</a>
              <p class="text-admin-muted text-xs">{{ s.headline }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-4">
          <h3 class="text-sm font-bold text-admin-text mb-3">SEO</h3>
          <dl class="space-y-2 text-sm">
            <div><dt class="text-admin-muted">Meta Title</dt><dd class="text-admin-text">{{ article.meta_title || '—' }}</dd></div>
            <div><dt class="text-admin-muted">Meta Description</dt><dd class="text-admin-text">{{ article.meta_description || '—' }}</dd></div>
            <div><dt class="text-admin-muted">Keywords</dt><dd class="text-xs text-admin-text">{{ article.meta_keywords || '—' }}</dd></div>
            <div><dt class="text-admin-muted">Read Time</dt><dd class="text-admin-text">{{ article.read_time_minutes }} min</dd></div>
            <div><dt class="text-admin-muted">Views</dt><dd class="text-admin-text">{{ article.views }}</dd></div>
            <div><dt class="text-admin-muted">Provider</dt><dd class="text-admin-text">{{ article.provider }}/{{ article.model }}</dd></div>
          </dl>
        </div>

        <div v-if="article.quality_report" class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-4">
          <h3 class="text-sm font-bold text-admin-text mb-3">Quality Report</h3>
          <div v-if="article.quality_report.metrics" class="text-xs space-y-1">
            <div v-for="(val, key) in article.quality_report.metrics" :key="key" class="flex justify-between">
              <span class="text-admin-muted">{{ key }}</span>
              <span class="text-admin-text">{{ val }}</span>
            </div>
          </div>
          <div v-if="article.quality_report.issues?.length" class="mt-3 space-y-1">
            <p v-for="issue in article.quality_report.issues" :key="issue" class="text-amber-700 text-xs">{{ issue }}</p>
          </div>
        </div>

        <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-4">
          <h3 class="text-sm font-bold text-admin-text mb-3">Status</h3>
          <div class="flex gap-2">
            <button v-for="s in ['published', 'draft', 'archived']" :key="s" @click="updateStatus(s)"
              :class="article.status === s ? 'bg-admin-sidebar text-white' : 'border border-admin-border text-slate-600 hover:border-slate-400 bg-admin-surface'"
              class="text-[10px] font-semibold uppercase tracking-wider px-3 py-1.5 rounded-full transition-colors"
            >{{ s }}</button>
          </div>
        </div>

        <div class="bg-admin-surface rounded-[14px] border border-red-100 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-4">
          <h3 class="text-sm font-bold text-admin-text mb-3 text-red-600">Danger Zone</h3>
          <button @click="deleteArticle"
            class="text-[10px] font-semibold uppercase tracking-wider px-3 py-1.5 border border-red-300 text-red-700 hover:bg-red-50 rounded-full transition-colors"
          >Delete Article</button>
        </div>
      </div>
    </div>
  </div>

  <div v-else class="text-center py-20 text-admin-muted">Loading article...</div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin' })

const route = useRoute()
const router = useRouter()
const api = useAdminApi()
const article = ref<any>(null)
const regenerating = ref(false)

const sanitizedContent = computed(() => {
  if (!article.value?.content) return ''
  return sanitizeHtml(article.value.content)
})

function statusClass(status: string) {
  if (status === 'published') return 'bg-emerald-50 text-emerald-700 border border-emerald-100'
  if (status === 'draft') return 'bg-amber-50 text-amber-700 border border-amber-100'
  return 'bg-slate-100 text-slate-600 border border-admin-border'
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
}

async function loadArticle() {
  try {
    const res = await api.getArticle(Number(route.params.id))
    article.value = res.data
  } catch {
    router.push('/admin/articles')
  }
}

async function updateStatus(status: string) {
  try {
    await api.updateArticle(article.value.id, { status })
    article.value.status = status
  } catch {}
}

async function regenerate() {
  if (!confirm('Regenerate?')) return
  regenerating.value = true
  try {
    await api.regenerateArticle(article.value.id)
    router.push('/admin/articles')
  } catch {}
  regenerating.value = false
}

async function deleteArticle() {
  if (!confirm('Delete this article permanently?')) return
  try {
    await api.deleteArticle(article.value.id)
    router.push('/admin/articles')
  } catch {}
}

onMounted(loadArticle)
</script>
