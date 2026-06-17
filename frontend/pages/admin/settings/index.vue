<template>
  <div class="space-y-5">
    <!-- System Settings -->
    <div class="bg-white rounded-2xl border border-gray-200/60 p-6">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-[#1a2233]/10 flex items-center justify-center">
          <Settings class="w-5 h-5 text-[#1a2233]" />
        </div>
        <div>
          <h2 class="text-sm font-bold text-gray-900">System Settings</h2>
          <p class="text-xs text-gray-500">Core engine configuration</p>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="item in systemItems" :key="item.label" class="p-4 rounded-xl bg-gray-50 border border-gray-100">
          <label class="block text-[11px] font-semibold uppercase text-gray-500 tracking-wider mb-1.5">{{ item.label }}</label>
          <p class="text-sm font-medium text-gray-900">{{ item.value }}</p>
        </div>
      </div>
    </div>

    <!-- Quality Gate -->
    <div class="bg-white rounded-2xl border border-gray-200/60 p-6">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
          <ShieldCheck class="w-5 h-5 text-emerald-600" />
        </div>
        <div>
          <h2 class="text-sm font-bold text-gray-900">Quality Gate</h2>
          <p class="text-xs text-gray-500">Automated article quality thresholds</p>
        </div>
      </div>
      <div class="space-y-3">
        <div v-for="item in qualityItems" :key="item.label" class="flex items-center justify-between py-3 border-b border-gray-50 last:border-0">
          <span class="text-sm text-gray-500">{{ item.label }}</span>
          <span class="text-sm font-medium text-gray-900">{{ item.value }}</span>
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