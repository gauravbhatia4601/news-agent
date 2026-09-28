<script setup lang="ts">
import type { AiModelRow } from './CostContextScatter.vue'
import { providerColor } from './providerColor'

// Vertical ranked top-10 — 10 self-contained columns on a shared baseline
// (flex-wrap, so mobile folds into two rows of five with zero JS).
// Deliberately NO y-axis/gridlines: every value is labeled on its cap, so
// ticks/grid would be noise — a top-10 ranking with all ten values shown IS
// the axis (a labeled table turned vertical).
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

// Drop the "Vendor: " prefix — the provider is carried by the bar color and
// the legend below. Cap at 16 chars; hover title always carries the full name.
function shortName(m: AiModelRow): string {
  const n = m.name.includes(': ') ? (m.name.split(': ')[1] ?? m.name) : m.name
  return n.length > 16 ? `${n.slice(0, 14)}…` : n
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end border-b border-border">
      <div
        v-for="(m, i) in top"
        :key="m.id"
        class="w-1/2 sm:w-[10%]"
        :title="`${m.name} — Intelligence Index ${fmt(m.intelligenceIndex as number)}`"
      >
        <div class="h-36 flex flex-col items-center justify-end">
          <span class="font-label text-[11px] font-bold tabular-nums mb-1 shrink-0">{{ fmt(m.intelligenceIndex as number) }}</span>
          <div
            class="w-6 rounded-t-[4px] shrink-0"
            :style="{ height: pct(m) + '%', background: providerColor(m.provider) }"
          />
        </div>
        <div class="mt-1.5 flex flex-col items-center">
          <span class="font-label text-[9px] text-muted-foreground tabular-nums">#{{ i + 1 }}</span>
          <span class="font-label text-[10px] text-muted-foreground truncate max-w-full" :title="m.name">{{ shortName(m) }}</span>
        </div>
      </div>
    </div>
    <p class="mt-3 font-label text-[11px] text-muted-foreground">
      Top {{ top.length }} of {{ scoredTotal ?? top.length }} scored · Intelligence Index by Artificial Analysis (via OpenRouter)
    </p>
  </div>
</template>