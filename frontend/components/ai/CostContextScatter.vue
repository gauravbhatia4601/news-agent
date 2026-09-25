<script setup lang="ts">
// Cost vs context window scatter — hand-rolled inline SVG, no chart library.
// Log-log axes: prices span ~0.04–300 $/M, contexts 8k–10M tokens.
export interface AiModelRow {
  id: string
  name: string
  provider: string
  contextLength: number
  pricePerMInput: number | null
  pricePerMOutput: number | null
  modality?: string
}

const props = defineProps<{ models: AiModelRow[] }>()

const W = 600
const H = 340
const PAD = 44

const points = computed(() =>
  props.models
    .filter((m) => m.pricePerMInput !== null && m.contextLength > 0)
    .map((m) => ({ ...m, price: m.pricePerMInput as number })),
)

// x: log10(context) from 3.8 (~6k) to 7.5 (~30M); y: log10(price) from -1.3 (~$0.05) to 2.5 (~$316)
const X_MIN = 3.8
const X_MAX = 7.5
const Y_MIN = -1.3
const Y_MAX = 2.5

function x(m: { contextLength: number }): number {
  const v = Math.log10(Math.max(m.contextLength, 1000))
  return PAD + ((v - X_MIN) / (X_MAX - X_MIN)) * (W - PAD * 1.6)
}
function y(price: number): number {
  const v = Math.log10(Math.max(price, 0.01))
  return H - PAD - ((v - Y_MIN) / (Y_MAX - Y_MIN)) * (H - PAD * 1.6)
}

const xTicks = [
  { v: 8000, label: '8k' },
  { v: 128000, label: '128k' },
  { v: 1000000, label: '1M' },
  { v: 10000000, label: '10M' },
]
const yTicks = [
  { v: 0.1, label: '$0.10' },
  { v: 1, label: '$1' },
  { v: 10, label: '$10' },
  { v: 100, label: '$100' },
]

// Label only extreme points so the chart stays readable.
const labeled = computed(() => {
  if (points.value.length === 0) return []
  const sorted = [...points.value].sort((a, b) => b.price - a.price)
  return [sorted[0], sorted[sorted.length - 1]].filter(Boolean)
})
</script>

<template>
  <div>
    <svg :viewBox="`0 0 ${W} ${H}`" class="w-full h-auto" role="img" aria-label="Cost per million input tokens versus context window, log-log scale">
      <!-- gridlines + tick labels -->
      <g stroke="currentColor" class="text-border" stroke-width="1">
        <line v-for="t in xTicks" :key="'x' + t.v" :x1="x({ contextLength: t.v })" :y1="PAD" :x2="x({ contextLength: t.v })" :y2="H - PAD" />
        <line v-for="t in yTicks" :key="'y' + t.v" :x1="PAD" :y1="y(t.v)" :x2="W - PAD / 2" :y2="y(t.v)" />
      </g>
      <g class="fill-current text-muted-foreground font-label" font-size="10">
        <text v-for="t in xTicks" :key="'xt' + t.v" :x="x({ contextLength: t.v })" :y="H - PAD + 14" text-anchor="middle">{{ t.label }}</text>
        <text v-for="t in yTicks" :key="'yt' + t.v" :x="PAD - 6" :y="y(t.v) + 3" text-anchor="end">{{ t.label }}</text>
      </g>
      <!-- axis titles -->
      <text :x="W / 2" :y="H - 6" text-anchor="middle" class="fill-current text-muted-foreground font-label" font-size="10">Context window</text>
      <text :x="12" :y="PAD + 4" class="fill-current text-muted-foreground font-label" font-size="10" transform="rotate(-90 12 60)">Input price $/M</text>

      <!-- points -->
      <g>
        <circle
          v-for="m in points"
          :key="m.id"
          :cx="x(m)"
          :cy="y(m.price)"
          r="4"
          class="fill-current text-accent"
        >
          <title>{{ m.name }} — {{ m.contextLength.toLocaleString() }} ctx, ${{ m.price.toFixed(2) }}/M input</title>
        </circle>
      </g>
      <g class="fill-current text-foreground font-label" font-size="10">
        <text v-for="m in labeled" :key="'l' + m.id" :x="x(m) + 6" :y="y(m.price) - 6">{{ m.name }}</text>
      </g>
    </svg>

    <!-- sr-only table: the crawlable + accessible mirror of the chart -->
    <table class="sr-only">
      <caption>Model cost versus context window</caption>
      <thead>
        <tr><th scope="col">Model</th><th scope="col">Context window</th><th scope="col">Input price per million tokens</th></tr>
      </thead>
      <tbody>
        <tr v-for="m in points" :key="'t' + m.id">
          <td>{{ m.name }}</td>
          <td>{{ m.contextLength.toLocaleString() }} tokens</td>
          <td>${{ m.price.toFixed(2) }} per million tokens</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>