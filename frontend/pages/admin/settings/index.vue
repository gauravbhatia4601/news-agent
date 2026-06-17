<template>
  <div class="space-y-6">
    <!-- System Settings -->
    <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-6">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-[#1a2233]/40 flex items-center justify-center">
          <Settings class="w-5 h-5 text-[#f5a623]" />
        </div>
        <div>
          <h2 class="text-[15px] font-semibold text-white">System Settings</h2>
          <p class="text-xs text-[#64748B] font-mono">Core engine configuration</p>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="item in systemItems" :key="item.label" class="p-4 rounded-[14px] bg-white/[0.04] border border-white/[0.06]">
          <label class="block text-[11px] font-semibold uppercase text-[#94A3B8] tracking-wider mb-1.5 font-mono">{{ item.label }}</label>
          <p class="text-sm font-medium text-white font-mono">{{ item.value }}</p>
        </div>
      </div>
    </div>

    <!-- Quality Gate -->
    <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-6">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center">
          <ShieldCheck class="w-5 h-5 text-emerald-400" />
        </div>
        <div>
          <h2 class="text-[15px] font-semibold text-white">Quality Gate</h2>
          <p class="text-xs text-[#64748B] font-mono">Automated article quality thresholds</p>
        </div>
      </div>
      <div class="space-y-3">
        <div v-for="item in qualityItems" :key="item.label" class="flex items-center justify-between py-3 border-b border-white/[0.04] last:border-0">
          <span class="text-sm text-[#94A3B8]">{{ item.label }}</span>
          <span class="text-sm font-medium text-white font-mono">{{ item.value }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Settings, ShieldCheck } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const systemItems = [
  { label: 'Generation Provider', value: 'ollama_cloud' },
  { label: 'Model', value: 'deepseek-v4-pro' },
  { label: 'Timeout', value: '300s' },
  { label: 'Min Words', value: '600' },
  { label: 'Discovery Window', value: '48 hours' },
  { label: 'Max Tokens', value: '16384' },
]

const qualityItems = [
  { label: 'Minimum Words', value: '600' },
  { label: 'Minimum Sections', value: '4' },
  { label: 'Active Voice Ratio', value: '≥ 55%' },
  { label: 'Attribution Count', value: '≥ 3' },
  { label: 'Hook Quality', value: 'Required' },
]
</script>
