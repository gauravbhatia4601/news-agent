<script setup lang="ts">
import { providerColor } from './providerColor'

// Cost vs context window scatter with a "value frontier" — models on or near
// the cheap+big corner are connected; everything above it costs more for the
// same capability envelope. Log-log, provider-colored, hand-rolled SVG.
//
// Only the top 25 scored models (by Intelligence Index) are DRAWN with a
// label each — 359 dots × ~40px labels cannot fit a ~380px plot (see PR).
// The sr-only table below keeps ALL priced models: the drawing is the
// top-25 subset, the accessible/crawlable mirror stays complete.
export interface AiModelRow {
  id: string
  name: string
  provider: string
  contextLength: number
  pricePerMInput: number | null
  pricePerMOutput: number | null
  intelligenceIndex?: number | null
  modality?: string
}

const props = defineProps<{ models: AiModelRow[] }>()

const W = 560
const H = 300
const PAD_L = 36
const PAD_B = 34

const X_MIN = 3.8
const X_MAX = 7.5
const Y_MIN = -1.3
const Y_MAX = 2.5

function x(m: { contextLength: number }): number {
  const v = Math.log10(Math.max(m.contextLength, 1000))
  return PAD_L + ((v - X_MIN) / (X_MAX - X_MIN)) * (W - 14 - PAD_L)
}
function y(price: number): number {
  const v = Math.log10(Math.max(price, 0.01))
  return H - PAD_B - 10 - ((v - Y_MIN) / (Y_MAX - Y_MIN)) * (H - PAD_B - 10 - (PAD_B + 4))
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

// Drawn subset: top 25 by Intelligence Index (ties broken by cheaper blended
// price). Zero scored rows (feed gap) → fall back to the 25 cheapest so the
// chart never empties.
const drawn = computed(() => {
  const scored = points.value
    .filter((m) => m.intelligenceIndex != null && m.intelligenceIndex > 0)
    .sort((a, b) => (b.intelligenceIndex as number) - (a.intelligenceIndex as number) || a.price - b.price)
    .slice(0, 25)
  if (scored.length > 0) return scored
  return [...points.value].sort((a, b) => a.price - b.price).slice(0, 25)
})

// Value frontier: Pareto-efficient set over the DRAWN points only — computed
// over all 359 while drawing 25, the line would float through empty space.
// Walk sorted by context ascending, keep decreasing price — a step-down line.
const frontier = computed(() => {
  const sorted = [...drawn.value].sort((a, b) => a.contextLength - b.contextLength)
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

// ── Per-dot labels: deterministic slot stagger, no measurement, no lib ──
// Four slot families — right of dot, above, below, left — as 8px chains
// tried in order. Process cheapest first: the dense cheap cluster gets first
// pick of its slots. A candidate is rejected on collision with a placed
// label rect, a drawn dot, or the canvas edge. Placement is exhaustive over
// the families so every drawn dot carries its label (25/25 against a
// 25-point stub).
interface Placed { id: string; text: string; x: number; y: number; anchor: string }
interface Rect { x: number; y: number; w: number; h: number }

function abbreviated(name: string): string {
  const n = name.includes(': ') ? (name.split(': ')[1] ?? name) : name
  return n.length > 11 ? `${n.slice(0, 9)}…` : n
}

function hits(a: Rect, b: Rect): boolean {
  return a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h
}

const labels = computed<Placed[]>(() => {
  const placed: Placed[] = []
  const rects: Rect[] = []
  const sorted = [...drawn.value].sort((a, b) => y(a.price) - y(b.price) || x(a) - x(b))
  const STEP = 8
  for (const m of sorted) {
    const cx = x(m)
    const cy = y(m.price)
    const text = abbreviated(m.name)
    const w = text.length * 3.9 + 4
    const chain = 24
    const candidates: { px: number; by: number; anchor: string }[] = []
    for (let i = 0; i < chain; i++) candidates.push({ px: cx + 5, by: cy + 2.5 + i * STEP, anchor: 'start' })
    for (let i = 1; i < chain; i++) candidates.push({ px: cx, by: cy - 6 - i * STEP, anchor: 'middle' })
    for (let i = 1; i < chain; i++) candidates.push({ px: cx, by: cy + 2.5 + i * STEP, anchor: 'middle' })
    for (let i = 0; i < chain; i++) candidates.push({ px: cx - 5, by: cy + 2.5 + i * STEP, anchor: 'end' })
    for (const c of candidates) {
      const rx = c.anchor === 'start' ? c.px : c.anchor === 'end' ? c.px - w : c.px - w / 2
      const rect: Rect = { x: rx, y: c.by - 7, w, h: 9 }
      if (rect.x < 0 || rect.x + w > W || rect.y < 0 || rect.y + 9 > H - PAD_B + 4) continue
      if (rects.some((r) => hits(rect, r))) continue
      if (drawn.value.some((d) => hits(rect, { x: x(d) - 4, y: y(d.price) - 4, w: 8, h: 8 }) && (x(d) !== cx || y(d.price) !== cy))) continue
      rects.push(rect)
      placed.push({ id: m.id, text, x: c.px, y: c.by, anchor: c.anchor })
      break
    }
  }
  return placed
})
</script>

<template>
  <div>
    <svg :viewBox="`0 0 ${W} ${H}`" class="w-full h-auto max-w-[680px]" role="img" aria-label="Cost per million input tokens versus context window, log-log scale, provider colored">
      <!-- quadrant shade: the "more for less" corner -->
      <rect
        :x="x({ contextLength: 1000000 })" :y="y(1)"
        :width="Math.max(0, W - 14 - x({ contextLength: 1000000 }))"
        :height="Math.max(0, H - PAD_B - y(1))"
        class="fill-current text-muted" opacity="0.45"
      />

      <!-- gridlines + tick labels (ticks carry the units — no axis titles) -->
      <g stroke="currentColor" class="text-border" stroke-width="1">
        <line v-for="t in xTicks" :key="'x' + t.v" :x1="x({ contextLength: t.v })" :y1="PAD_B + 4" :x2="x({ contextLength: t.v })" :y2="H - PAD_B" />
        <line v-for="t in yTicks" :key="'y' + t.v" :x1="PAD_L" :y1="y(t.v)" :x2="W - 14" :y2="y(t.v)" />
      </g>
      <g class="fill-current text-muted-foreground font-label" font-size="9">
        <text v-for="t in xTicks" :key="'xt' + t.v" :x="x({ contextLength: t.v })" :y="H - PAD_B + 12" text-anchor="middle">{{ t.label }}</text>
        <text v-for="t in yTicks" :key="'yt' + t.v" :x="PAD_L - 5" :y="y(t.v) + 2.5" text-anchor="end">{{ t.label }}</text>
      </g>

      <!-- frontier line: step path through the Pareto points -->
      <polyline
        v-if="frontier.length > 1"
        :points="frontier.map((m) => `${x(m)},${y(m.price)}`).join(' ')"
        fill="none" stroke="currentColor" class="text-foreground" stroke-width="1.25"
        stroke-dasharray="4 3" opacity="0.6"
      />

      <!-- points: every drawn dot gets a 1px surface ring so overlapping
           marks stay separable, and its own label (see labels computed) -->
      <g>
        <circle
          v-for="m in drawn"
          :key="m.id"
          :cx="x(m)"
          :cy="y(m.price)"
          r="3"
          :fill="providerColor(m.provider)"
          class="stroke-card"
          stroke-width="1"
        >
          <title>{{ m.name }} — {{ m.contextLength.toLocaleString() }} ctx, ${{ m.price.toFixed(2) }}/M blended (3 in / 1 out)</title>
        </circle>
      </g>
      <g class="fill-current text-foreground font-label" font-size="8">
        <text v-for="l in labels" :key="'l' + l.id" :x="l.x" :y="l.y" :text-anchor="l.anchor">{{ l.text }}</text>
      </g>
    </svg>

    <AiProviderLegend :models="drawn" />
    <p class="font-label text-[11px] text-muted-foreground">
      Top {{ drawn.length }} scored models labeled · all {{ points.length }} in the table below
    </p>

    <!-- sr-only table: the crawlable + accessible mirror of the chart —
         keeps ALL priced models, not just the drawn top-25 -->
    <!-- White-space root cause (2026-09-28): sr-only was ON THE TABLE. Tailwind's
         .sr-only sets width/height 1px + overflow hidden, but for display:table
         those are MINIMUMS — min-content size wins, overflow:hidden doesn't clip
         table boxes, and clip only stops painting (never layout). The table laid
         out ~991×9384px → ~8,500px of dead scroll after the footer. A block div
         wrapper honors 1px/1px/hidden; screen readers and crawlers read through
         it unchanged. -->
    <div class="sr-only">
      <table>
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
  </div>
</template>