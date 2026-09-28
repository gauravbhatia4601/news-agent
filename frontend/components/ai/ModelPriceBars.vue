<script setup lang="ts">
import type { AiModelRow } from './CostContextScatter.vue'
import { providerColor } from './providerColor'

// Ranked pricing list, artificialanalysis-style: one row per model, one
// headline number (blended price, 3:1 input:output — the standard weighting),
// with input/output as secondary columns. Cheapest first. Log-scale bars keep
// a $0.15 and a $67.50 model comparable on one axis.
const props = defineProps<{ models: AiModelRow[] }>()

const floor = 0.05

function blended(m: AiModelRow): number {
  const input = m.pricePerMInput ?? m.pricePerMOutput ?? 0
  const output = m.pricePerMOutput ?? input
  return input * 0.75 + output * 0.25
}
const ranked = computed(() =>
  props.models
    .filter((m) => blended(m) > 0)
    .sort((a, b) => blended(a) - blended(b)),
)
const max = computed(() => Math.max(...ranked.value.map(blended), floor * 2))
function pct(p: number): number {
  const v = Math.log10(p / floor) / Math.log10(max.value / floor)
  return Math.min(100, Math.max(2, Math.round(v * 100)))
}
function fmt(p: number): string {
  return p >= 1 ? `$${p.toFixed(2)}` : `$${p.toFixed(2)}`
}
</script>

<template>
  <div>
    <div class="space-y-2.5">
      <div
        v-for="(m, i) in ranked"
        :key="m.id"
        class="grid grid-cols-[28px_minmax(0,190px)_1fr_128px] items-center gap-3"
      >
        <span class="font-label text-[11px] text-muted-foreground tabular-nums text-right">{{ i + 1 }}</span>
        <div class="flex items-center gap-2 min-w-0">
          <span class="inline-block w-2.5 h-2.5 rounded-sm shrink-0" :style="{ background: providerColor(m.provider) }" :title="m.provider" />
          <span class="font-label text-xs truncate" :title="m.name">{{ m.name }}</span>
        </div>
        <div class="bg-muted h-2 rounded-full overflow-hidden">
          <div class="h-full rounded-full" :style="{ width: pct(blended(m)) + '%', background: providerColor(m.provider) }" />
        </div>
        <span class="font-label text-xs tabular-nums text-right whitespace-nowrap">
          {{ fmt(blended(m)) }}/M
          <span class="text-muted-foreground text-[10px]">({{ fmt(m.pricePerMInput ?? 0) }} in / {{ fmt(m.pricePerMOutput ?? 0) }} out)</span>
        </span>
      </div>
    </div>
    <p class="mt-3 font-label text-[11px] text-muted-foreground">
      Ranked cheapest first · blended price = 3× input + 1× output, per million tokens · swatch = provider
    </p>
  </div>
</template>