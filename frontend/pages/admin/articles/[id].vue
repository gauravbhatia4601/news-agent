<template>
  <div v-if="article" class="space-y-6">
    <NuxtLink to="/admin/articles" class="text-sm text-zinc-500 hover:text-black transition-colors">&larr; Back to Articles</NuxtLink>

    <div class="border border-black p-6">
      <div class="flex items-start justify-between gap-4 mb-4">
        <div class="min-w-0 flex-1">
          <h1 class="font-display text-2xl font-bold">{{ article.title }}</h1>
          <div class="flex items-center gap-3 mt-2 text-sm text-zinc-500">
            <span>By {{ article.author }}</span>
            <span>&middot;</span>
            <span>{{ article.category || 'Uncategorized' }}</span>
            <span>&middot;</span>
            <span>{{ formatDate(article.created_at) }}</span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span class="font-label text-[10px] font-semibold uppercase tracking-wider px-2 py-1" :class="statusClass(article.status)">{{ article.status }}</span>
          <button @click="regenerate" :disabled="regenerating"
            class="font-label text-[10px] font-semibold uppercase tracking-wider px-3 py-1 border border-zinc-300 hover:border-black transition-colors disabled:opacity-50"
          >{{ regenerating ? 'Regenerating...' : 'Regenerate' }}</button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <div class="border border-black p-6">
          <h2 class="font-display font-bold mb-4">Content</h2>
          <div class="prose prose-sm max-w-none" v-html="article.content" />
        </div>

        <div v-if="article.sources?.length" class="border border-black p-6">
          <h2 class="font-display font-bold mb-4">Sources</h2>
          <div class="space-y-2">
            <div v-for="s in article.sources" :key="s.id" class="text-sm">
              <a :href="s.source_url" target="_blank" class="text-blue-700 hover:underline">{{ s.source_name }}</a>
              <p class="text-zinc-500 text-xs">{{ s.headline }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="border border-black p-4">
          <h3 class="font-display font-bold text-sm mb-3">SEO</h3>
          <dl class="space-y-2 text-sm">
            <div><dt class="text-zinc-500">Meta Title</dt><dd>{{ article.meta_title || '—' }}</dd></div>
            <div><dt class="text-zinc-500">Meta Description</dt><dd>{{ article.meta_description || '—' }}</dd></div>
            <div><dt class="text-zinc-500">Keywords</dt><dd class="text-xs">{{ article.meta_keywords || '—' }}</dd></div>
            <div><dt class="text-zinc-500">Read Time</dt><dd>{{ article.read_time_minutes }} min</dd></div>
            <div><dt class="text-zinc-500">Views</dt><dd>{{ article.views }}</dd></div>
            <div><dt class="text-zinc-500">Provider</dt><dd>{{ article.provider }}/{{ article.model }}</dd></div>
          </dl>
        </div>

        <div v-if="article.quality_report" class="border border-black p-4">
          <h3 class="font-display font-bold text-sm mb-3">Quality Report</h3>
          <div v-if="article.quality_report.metrics" class="text-xs space-y-1">
            <div v-for="(val, key) in article.quality_report.metrics" :key="key" class="flex justify-between">
              <span class="text-zinc-500">{{ key }}</span>
              <span>{{ val }}</span>
            </div>
          </div>
          <div v-if="article.quality_report.issues?.length" class="mt-3 space-y-1">
            <p v-for="issue in article.quality_report.issues" :key="issue" class="text-amber-700 text-xs">{{ issue }}</p>
          </div>
        </div>

        <div class="border border-black p-4">
          <h3 class="font-display font-bold text-sm mb-3">Status</h3>
          <div class="flex gap-2">
            <button v-for="s in ['published', 'draft', 'archived']" :key="s" @click="updateStatus(s)"
              :class="article.status === s ? 'bg-black text-white' : 'border border-zinc-300 text-zinc-600 hover:border-black'"
              class="font-label text-[10px] font-semibold uppercase tracking-wider px-3 py-1.5 transition-colors"
            >{{ s }}</button>
          </div>
        </div>

        <div class="border border-red-900/20 p-4">
          <h3 class="font-display font-bold text-sm mb-3 text-red-700">Danger Zone</h3>
          <button @click="deleteArticle"
            class="font-label text-[10px] font-semibold uppercase tracking-wider px-3 py-1.5 border border-red-300 text-red-700 hover:bg-red-50 transition-colors"
          >Delete Article</button>
        </div>
      </div>
    </div>
  </div>

  <div v-else class="text-center py-20 text-zinc-500">Loading article...</div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin' })

const route = useRoute()
const router = useRouter()
const api = useAdminApi()
const article = ref<any>(null)
const regenerating = ref(false)

function statusClass(status: string) {
  if (status === 'published') return 'bg-emerald-100 text-emerald-800'
  if (status === 'draft') return 'bg-amber-100 text-amber-800'
  return 'bg-zinc-100 text-zinc-600'
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