<template>
  <div class="space-y-6">
    <div class="border border-black p-6">
      <h2 class="font-display font-bold text-lg mb-4">Trigger Discovery</h2>
      <p class="text-sm text-zinc-500 mb-4">Manually trigger a news discovery run across all categories.</p>

      <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
          <label class="font-label text-[10px] font-semibold uppercase tracking-[0.062em] text-zinc-500">Topics per category</label>
          <input v-model.number="discoveryParams.limit" type="number" min="1" max="20"
            class="w-full border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:border-black" />
        </div>
        <div>
          <label class="font-label text-[10px] font-semibold uppercase tracking-[0.062em] text-zinc-500">Freshness (hours)</label>
          <input v-model.number="discoveryParams.fresh_hours" type="number" min="1" max="48"
            class="w-full border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:border-black" />
        </div>
        <div>
          <label class="font-label text-[10px] font-semibold uppercase tracking-[0.062em] text-zinc-500">Sources per topic</label>
          <input v-model.number="discoveryParams.sources_per_topic" type="number" min="2" max="6"
            class="w-full border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:border-black" />
        </div>
      </div>

      <button @click="triggerDiscovery" :disabled="discovering"
        class="font-label text-[10px] font-semibold uppercase tracking-[0.062em] px-4 py-2 bg-black text-white hover:bg-zinc-800 transition-colors disabled:opacity-50"
      >
        {{ discovering ? 'Discovering...' : 'Start Discovery' }}
      </button>
    </div>

    <div v-if="discoveryResult" class="border border-emerald-900/20 p-6 bg-emerald-50">
      <h3 class="font-display font-bold text-emerald-800 mb-3">Discovery Result</h3>
      <div class="grid grid-cols-3 gap-4 mb-4 text-sm">
        <div><span class="text-zinc-500">Discovered:</span> {{ discoveryResult.discovered }}</div>
        <div><span class="text-zinc-500">Queued:</span> {{ discoveryResult.queued_for_generation }}</div>
      </div>
      <div v-if="discoveryResult.topics?.length" class="space-y-2 mt-4">
        <p class="font-label text-[10px] font-semibold uppercase tracking-[0.062em] text-zinc-500">New Topics:</p>
        <div v-for="t in discoveryResult.topics" :key="t.signature" class="text-sm border border-zinc-200 p-2 bg-white">
          <span class="text-zinc-400">{{ t.category }}:</span> {{ t.name }}
          <span class="text-xs text-zinc-500">({{ t.source_count }} sources)</span>
        </div>
      </div>
    </div>

    <div class="border border-black p-6">
      <h2 class="font-display font-bold text-lg mb-4">Retry Failed Topics</h2>
      <p class="text-sm text-zinc-500 mb-4">Reset all failed topics and queue them for regeneration.</p>
      <button @click="retryAllFailed" :disabled="retrying"
        class="font-label text-[10px] font-semibold uppercase tracking-[0.062em] px-4 py-2 border border-amber-300 text-amber-700 hover:bg-amber-50 transition-colors disabled:opacity-50"
      >
        {{ retrying ? 'Retrying...' : 'Retry All Failed' }}
      </button>
      <div v-if="retryResult" class="mt-3 text-sm text-emerald-700">
        Retried {{ retryResult.retried }} topics.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const discovering = ref(false)
const retrying = ref(false)
const discoveryResult = ref<any>(null)
const retryResult = ref<any>(null)

const discoveryParams = reactive({ limit: 3, fresh_hours: 24, sources_per_topic: 3 })

async function triggerDiscovery() {
  discovering.value = true
  discoveryResult.value = null
  try {
    const res = await api.triggerDiscovery(discoveryParams)
    discoveryResult.value = res.data
  } catch (e: any) {
    alert('Discovery failed: ' + (e?.data?.message || 'Unknown error'))
  }
  discovering.value = false
}

async function retryAllFailed() {
  retrying.value = true
  retryResult.value = null
  try {
    const res = await api.retryFailed()
    retryResult.value = res.data
  } catch (e: any) {
    alert('Retry failed: ' + (e?.data?.message || 'Unknown error'))
  }
  retrying.value = false
}
</script>