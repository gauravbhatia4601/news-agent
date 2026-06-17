<template>
  <div v-if="topic" class="space-y-6">
    <NuxtLink to="/admin/topics" class="text-sm text-slate-500 hover:text-slate-900">&larr; Back to Topics</NuxtLink>

    <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">{{ topic.topic_name }}</h1>
          <div class="flex items-center gap-3 mt-2 text-sm text-slate-500">
            <span>{{ topic.category }}</span>
            <span>&middot;</span>
            <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="topicStatusClass(topic.generation_status)">{{ topic.generation_status }}</span>
            <span>&middot;</span>
            <span>{{ topic.source_count }} sources</span>
          </div>
        </div>
        <div class="flex gap-2">
          <button v-if="topic.generation_status === 'failed'" @click="retry"
            class="text-xs px-3 py-1.5 border border-amber-300 text-amber-700 rounded-full hover:bg-amber-50 transition-colors"
          >Retry</button>
          <button @click="deleteTopic"
            class="text-xs px-3 py-1.5 border border-red-300 text-red-700 rounded-full hover:bg-red-50 transition-colors"
          >Delete</button>
        </div>
      </div>
    </div>

    <div v-if="topic.article" class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-[15px] font-bold text-slate-900">Generated Article</h2>
        <NuxtLink :to="`/admin/articles/${topic.article.id}`" class="text-xs text-blue-600 hover:underline">View Article &rarr;</NuxtLink>
      </div>
      <dl class="grid grid-cols-2 gap-2 text-sm">
        <div><dt class="text-slate-500">Title</dt><dd class="text-slate-900">{{ topic.article.title }}</dd></div>
        <div>
          <dt class="text-slate-500">Status</dt>
          <dd><span class="text-xs px-2 py-0.5 rounded-full" :class="topic.article.status === 'published' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">{{ topic.article.status }}</span></dd>
        </div>
      </dl>
    </div>

    <div v-if="topic.sources?.length" class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <h2 class="text-[15px] font-bold text-slate-900 mb-4">Sources ({{ topic.sources.length }})</h2>
      <div class="space-y-3">
        <div v-for="s in topic.sources" :key="s.id" class="border border-slate-200 rounded-[14px] p-3">
          <a :href="s.source_url" target="_blank" class="text-sm font-medium text-slate-900 hover:text-blue-600 transition-colors">{{ s.headline || s.source_name }}</a>
          <p class="text-xs text-slate-500 mt-1">{{ s.source_name }} · {{ s.published_at ? formatDate(s.published_at) : 'N/A' }}</p>
          <p v-if="s.summary" class="text-xs text-slate-400 mt-1 line-clamp-2">{{ s.summary }}</p>
        </div>
      </div>
    </div>
  </div>

  <div v-else class="text-center py-20 text-slate-500">Loading topic...</div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin' })

const route = useRoute()
const router = useRouter()
const api = useAdminApi()
const topic = ref<any>(null)

function topicStatusClass(s: string) {
  if (s === 'generated') return 'bg-emerald-100 text-emerald-700'
  if (s === 'pending') return 'bg-blue-100 text-blue-700'
  if (s === 'failed') return 'bg-red-100 text-red-700'
  return 'bg-slate-100 text-slate-600'
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

async function loadTopic() {
  try {
    const res = await api.getTopic(Number(route.params.id))
    topic.value = res.data
  } catch {
    router.push('/admin/topics')
  }
}

async function retry() {
  try {
    await api.retryTopic(topic.value.id)
    await loadTopic()
  } catch {}
}

async function deleteTopic() {
  if (!confirm('Delete this topic and all associated data?')) return
  try {
    await api.deleteTopic(topic.value.id)
    router.push('/admin/topics')
  } catch {}
}

onMounted(loadTopic)
</script>
