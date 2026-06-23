<script setup lang="ts">
const api = useNewsApi()
const { data: indices } = await useAsyncData('market-ticker', () => api.getMarketData(), {
  default: () => [] as any[],
  server: true,
  lazy: true,
})

function formatPrice(price: number, currency: string): string {
  if (currency === 'INR') {
    return new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 }).format(price)
  }
  return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(price)
}

function formatChange(change: number, percent: number): string {
  const sign = change >= 0 ? '+' : ''
  return `${sign}${percent.toFixed(2)}%`
}

function directionClass(change: number): string {
  return change >= 0 ? 'text-emerald-600' : 'text-red-600'
}

function arrow(change: number): string {
  return change >= 0 ? '▲' : '▼'
}
</script>

<template>
  <div v-if="indices && indices.length > 0" class="w-full bg-white text-primary-foreground overflow-hidden border-b border-white/10">
    <div class="ticker-track flex items-center gap-8 py-1.5 whitespace-nowrap font-label text-xs">
      <div
        v-for="(idx, i) in [...indices, ...indices]"
        :key="`${idx.symbol}-${i}`"
        class="flex items-center gap-2 shrink-0"
      >
        <span class="font-bold uppercase tracking-wider text-black/90">{{ idx.name }}</span>
        <span class="text-black/70">{{ formatPrice(idx.price, idx.currency) }}</span>
        <span :class="directionClass(idx.change)" class="font-semibold flex items-center gap-0.5">
          <span class="text-[8px]">{{ arrow(idx.change) }}</span>
          {{ formatChange(idx.change, idx.change_percent) }}
        </span>
        <span class="text-black/20">|</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ticker-track {
  animation: ticker-scroll 60s linear infinite;
}

@keyframes ticker-scroll {
  0% {
    transform: translateX(0);
  }
  100% {
    transform: translateX(-50%);
  }
}

.ticker-track:hover {
  animation-play-state: paused;
}

@media (prefers-reduced-motion: reduce) {
  .ticker-track {
    animation: none;
    overflow-x: auto;
    scrollbar-width: none;
  }
  .ticker-track::-webkit-scrollbar {
    display: none;
  }
}
</style>