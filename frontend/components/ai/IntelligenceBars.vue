<script setup lang="ts">
import type { AiModelRow } from './CostContextScatter.vue'
import { providerColor } from './providerColor'

// Intelligence Index vertical bars — every scored model, smartest first.
// Compact columns; hover gives the exact number. Pure CSS.
const props = defineProps<{ models: AiModelRow[] }>()

const scored = computed(() =>
  props.models
    .filter((m) => m.intelligenceIndex !== null && m.intelligenceIndex > 0)
    .sort((a, b) => (b.intelligenceIndex as number) - (a.intelligenceIndex as number))
    .slice(0, 20),
)

const max = computed(() => Math.max(...scored.value.map((m) => m.intelligenceIndex as number), 1))

function pct(m: AiModelRow): number {
  return Math.round(((m.intelligenceIndex as number) / max.value) * 100)
}

function fmt(v: number): string {
  return v.toFixed(1)
}
</script>

<template>
  <div>
    <div class="flex items-end gap-x-1.5 gap-y-4 flex-wrap">
      <div
        v-for="m in scored"
        :key="m.id"
        class="w-[52px] flex flex-col items-center"
        :title="`${m.name} — Intelligence Index ${fmt(m.intelligenceIndex as number)}`"
      >
        <span class="font-label text-[10px] font-bold tabular-nums">{{ fmt(m.intelligenceIndex as number) }}</span>
        <div class="w-5 bg-muted rounded-t-sm overflow-hidden flex items-end" style="height: 64px">
          <div
            class="w-full rounded-t-sm"
            :style="{ height: pct(m) + '%', background: providerColor(m.provider) }"
          />
        </div>
        <span class="font-label text-[9px] text-muted-foreground text-center leading-tight mt-1 line-clamp-2" :title="m.name">
          {{ m.name }}
        </span>
      </div>
    </div>
    <p class="mt-3 font-label text-[11px] text-muted-foreground">
      Top 20, smartest first · Intelligence Index (Artificial Analysis, via OpenRouter) · swatch = provider
    </p>
  </div>
</template>