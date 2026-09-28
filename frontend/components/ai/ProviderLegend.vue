<script setup lang="ts">
import { providerColor } from './providerColor'

// Provider legend — one swatch per distinct provider present in the rows.
const props = defineProps<{ models: { provider: string }[] }>()

const unique = computed(() => {
  const seen = new Map<string, string>()
  for (const m of props.models) seen.set(m.provider, providerColor(m.provider))
  return [...seen.entries()].map(([name, color]) => ({ name, color }))
})
</script>

<template>
  <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 font-label text-[11px] text-muted-foreground">
    <span v-for="p in unique" :key="p.name" class="flex items-center gap-1.5">
      <span class="inline-block w-2.5 h-2.5 rounded-sm" :style="{ background: p.color }" />
      {{ p.name }}
    </span>
  </div>
</template>