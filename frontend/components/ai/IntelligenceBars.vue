<script setup lang="ts">
import type { AiModelRow } from './CostContextScatter.vue'
import { providerColor } from './providerColor'

// Ranked top-10 intelligence rows — the artificialanalysis pattern:
// rank | swatch | name | bar | score. Horizontal, linear scale, compact.
const props = defineProps<{ models: AiModelRow[]; scoredTotal?: number }>()

const top = computed(() =>
  props.models
    .filter((m) => m.intelligenceIndex !== null && m.intelligenceIndex > 0)
    .sort((a, b) => (b.intelligenceIndex as number) - (a.intelligenceIndex as number))
    .slice(0, 10),
)

const max = computed(() => Math.max(...top.value.map((m) => m.intelligenceIndex as number), 1))

function pct(m: AiModelRow): number {
  return Math.round(((m.intelligenceIndex as number) / max.value) * 100)
}

function fmt(v: number): string {
  return v.toFixed(1)
}
</script>

<template>
  <div>
    <div class="space-y-2">
      <div
        v-for="(m, i) in top"
        :key="m.id"
        class="grid grid-cols-[24px_16px_minmax(0,220px)_1fr_56px] items-center gap-2.5"
        :title="`${m.name} — Intelligence Index ${fmt(m.intelligenceIndex as number)}`"
      >
        <span class="font-label text-[11px] text-muted-foreground tabular-nums text-right">{{ i + 1 }}</span>
        <span class="inline-block w-2.5 h-2.5 rounded-sm" :style="{ background: providerColor(m.provider) }" :title="m.provider" />
        <span class="font-label text-xs truncate" :title="m.name">{{ m.name }}</span>
        <div class="bg-muted h-2 rounded-full overflow-hidden">
          <div class="h-full rounded-full" :style="{ width: pct(m) + '%', background: providerColor(m.provider) }" />
        </div>
        <span class="font-label text-xs font-bold tabular-nums text-right">{{ fmt(m.intelligenceIndex as number) }}</span>
      </div>
    </div>
    <p class="mt-3 font-label text-[11px] text-muted-foreground">
      Top {{ top.length }} of {{ scoredTotal ?? top.length }} scored · Intelligence Index by Artificial Analysis (via OpenRouter)
    </p>
  </div>
</template>