<script setup lang="ts">
import type { StoryUrgency } from '~/types/news'

const props = defineProps<{
  urgency: StoryUrgency
}>()

const config: Record<StoryUrgency, { label: string; dot: string; text: string; pulse: boolean }> = {
  live: { label: 'LIVE', dot: 'bg-red-500', text: 'text-red-600', pulse: true },
  developing: { label: 'DEVELOPING', dot: 'bg-amber-500', text: 'text-amber-600', pulse: false },
  ongoing: { label: 'ONGOING', dot: 'bg-blue-500', text: 'text-blue-600', pulse: false },
  concluded: { label: 'CONCLUDED', dot: 'bg-slate-400', text: 'text-slate-500', pulse: false },
}

const c = computed(() => config[props.urgency] ?? config.ongoing)
</script>

<template>
  <span class="inline-flex items-center gap-1.5 font-label text-[11px] font-bold uppercase tracking-[0.062em]" :class="c.text">
    <span class="relative inline-flex">
      <span v-if="c.pulse" class="absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping" :class="c.dot"></span>
      <span class="relative inline-flex w-1.5 h-1.5 rounded-full" :class="c.dot"></span>
    </span>
    {{ c.label }}
  </span>
</template>