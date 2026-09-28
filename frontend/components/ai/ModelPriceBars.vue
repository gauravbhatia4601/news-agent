<script setup lang="ts">
import type { AiModelRow } from './CostContextScatter.vue'
import { providerColor } from './providerColor'

// Vertical grouped price bars — input (solid) + output (50% tint) per model,
// log scale, colored by provider. Pure CSS, no chart library.
const props = defineProps<{ models: AiModelRow[] }>()

const floor = 0.05

function priceOf(m: AiModelRow): number {
  return m.pricePerMInput ?? m.pricePerMOutput ?? 0
}
const priced = computed(() =>
  props.models.filter((m) => priceOf(m) > 0).sort((a, b) => priceOf(a) - priceOf(b)),
)
const max = computed(() => Math.max(...priced.value.map(priceOf), floor * 2))
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
    <div class="flex items-end gap-x-3 gap-y-6 flex-wrap">
      <div
        v-for="m in priced"
        :key="m.id"
        class="w-[74px] flex flex-col items-center"
        :title="`${m.name} — input $${fmt(m.pricePerMInput ?? 0)}/M, output $${fmt(m.pricePerMOutput ?? 0)}/M`"
      >
        <span class="font-label text-[10px] font-bold tabular-nums mb-1">{{ fmt(m.pricePerMOutput ?? 0) }}</span>
        <div class="flex items-end gap-[3px] h-32">
          <div
            class="w-4 rounded-t-sm"
            :style="{ height: pct(m.pricePerMInput ?? 0) + '%', background: providerColor(m.provider) }"
          />
          <div
            v-if="m.pricePerMOutput"
            class="w-4 rounded-t-sm"
            :style="{ height: pct(m.pricePerMOutput) + '%', background: providerColor(m.provider), opacity: 0.45 }"
          />
        </div>
        <span class="font-label text-[10px] text-muted-foreground text-center leading-tight mt-1.5 line-clamp-2" :title="m.name">
          {{ m.name }}
        </span>
        <span class="font-label text-[9px] text-muted-foreground tabular-nums">{{ fmt(m.pricePerMInput ?? 0) }}</span>
      </div>
    </div>
    <div class="flex items-center gap-4 mt-3 font-label text-[11px] text-muted-foreground">
      <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-2.5 rounded-sm bg-muted-foreground" /> input</span>
      <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-2.5 rounded-sm bg-muted-foreground opacity-50" /> output (faded)</span>
      <span>log scale · $/M tokens</span>
    </div>
  </div>
</template>