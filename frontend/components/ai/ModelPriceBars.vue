<script setup lang="ts">
import type { AiModelRow } from '~/components/ai/CostContextScatter.vue'

const props = defineProps<{ models: AiModelRow[] }>()

// Log scale — real prices span ~0.04–300 $/M; a linear bar makes cheap models invisible.
const floor = 0.05
function priceOf(m: AiModelRow): number {
  return m.pricePerMInput ?? m.pricePerMOutput ?? 0
}
const priced = computed(() =>
  props.models.filter((m) => priceOf(m) > 0).sort((a, b) => priceOf(a) - priceOf(b)),
)
const max = computed(() => Math.max(...priced.value.map(priceOf), floor * 2))
function pct(m: AiModelRow): number {
  const p = priceOf(m)
  const v = Math.log10(p / floor) / Math.log10(max.value / floor)
  return Math.min(100, Math.max(2, Math.round(v * 100)))
}
function fmt(p: number): string {
  return p >= 1 ? `$${p.toFixed(2)}` : `$${p.toFixed(2)}`
}
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="m in priced"
      :key="m.id"
      class="grid grid-cols-[130px_1fr_72px] items-center gap-3"
    >
      <span class="font-label text-xs text-muted-foreground truncate" :title="m.name">{{ m.name }}</span>
      <div class="bg-muted h-2 rounded-full overflow-hidden">
        <div class="bg-primary h-full rounded-full" :style="{ width: pct(m) + '%' }" />
      </div>
      <span class="font-label text-xs font-bold tabular-nums text-right">{{ fmt(priceOf(m)) }}/M</span>
    </div>
  </div>
</template>