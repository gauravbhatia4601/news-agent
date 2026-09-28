<script setup lang="ts">
import { providerColor } from './providerColor'

// Cost vs context window scatter with a "value frontier" — models on or near
// the cheap+big corner are connected; everything above it costs more for the
// same capability envelope. Log-log, provider-colored, hand-rolled SVG.
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

const points = computed(() =>
  props.models
    .filter((m) => m.pricePerMInput !== null && m.contextLength > 0)
    // Blended price (3×input + 1×output) — same headline metric as the ranked
    // pricing list above, so both charts tell one consistent story.
    .map((m) => ({
      ...m,
      price: (m.pricePerMInput as number) * 0.75 + (m.pricePerMOutput ?? (m.pricePerMInput as number)) * 0.25,
    })),
)

// Value frontier: Pareto-efficient set. A point is on the frontier if no other
// point is both cheaper AND wider-context (log-space). Walk sorted by context
// ascending, keep decreasing price — a step-down line = the value frontier.
const frontier = computed(() => {
  const sorted = [...points.value].sort((a, b) => a.contextLength - b.contextLength)
  const out: typeof sorted = []
  let bestPrice = Infinity
  for (const p of sorted) {
    if (p.price < bestPrice) {
      out.push(p)
      bestPrice = p.price
    }
  }
  return out
})

// Label frontier points + the most expensive point; never more than 5 labels.
const labeled = computed(() => {
  const extras = [...points.value]
    .sort((a, b) => b.price - a.price)
    .slice(0, 2)
  const merged = [...frontier.value, ...extras]
  const seen = new Set<string>()
  return merged.filter((m) => (seen.has(m.id) ? false : (seen.add(m.id), true))).slice(0, 5)
})
</script>

<template>
  <div>
    <svg :viewBox="`0 0 ${W} ${H}`" class="w-full h-auto" role="img" aria-label="Cost per million input tokens versus context window, log-log scale, provider colored">
      <!-- quadrant shade: the "more for less" corner -->
      <rect
        :x="x({ contextLength: 1000000 })" :y="y(1)"
        :width="Math.max(0, W - PAD * 0.6 - x({ contextLength: 1000000 }))"
        :height="Math.max(0, y(1) - PAD)"
        class="fill-current text-muted" opacity="0.45"
      />
      <text :x="x({ contextLength: 1000000 }) + 8" :y="y(1) - 8" class="fill-current text-muted-foreground font-label" font-size="10" opacity="0.9">more context, under $1/M — best value zone</text>

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

      <!-- frontier line: step path through the Pareto points -->
      <polyline
        v-if="frontier.length > 1"
        :points="frontier.map((m) => `${x(m)},${y(m.price)}`).join(' ')"
        fill="none" stroke="currentColor" class="text-foreground" stroke-width="1.5"
        stroke-dasharray="4 3" opacity="0.6"
      />

      <!-- points -->
      <g>
        <circle
          v-for="m in points"
          :key="m.id"
          :cx="x(m)"
          :cy="y(m.price)"
          r="4.5"
          :fill="providerColor(m.provider)"
          :stroke="frontier.some((f) => f.id === m.id) ? 'currentColor' : 'none'"
          stroke-width="1.5"
          class="text-foreground"
        >
          <title>{{ m.name }} — {{ m.contextLength.toLocaleString() }} ctx, ${{ m.price.toFixed(2) }}/M blended (3 in / 1 out)</title>
        </circle>
      </g>
      <g class="fill-current text-foreground font-label" font-size="10">
        <text v-for="m in labeled" :key="'l' + m.id" :x="x(m) + 6" :y="y(m.price) - 6">{{ m.name }}</text>
      </g>
    </svg>

    <NewsProviderLegend :models="points" />

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
          <td>${{ m.price.toFixed(2) }} per million tokens, blended (3×input + 1×output)</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>