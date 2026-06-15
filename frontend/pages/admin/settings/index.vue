<template>
  <div class="space-y-6">
    <div class="bg-white rounded-lg border border-slate-200 p-6">
      <h2 class="font-display font-bold text-lg mb-4">System Settings</h2>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Generation Provider</label>
            <p class="text-sm">ollama_cloud</p>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Model</label>
            <p class="text-sm">deepseek-v4-pro</p>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Timeout</label>
            <p class="text-sm">300s</p>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Min Words</label>
            <p class="text-sm">600</p>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Discovery Window</label>
            <p class="text-sm">48 hours</p>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Max Tokens</label>
            <p class="text-sm">16384</p>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 p-6">
      <h2 class="font-display font-bold text-lg mb-4">Quality Gate</h2>
      <div class="space-y-2 text-sm">
        <div class="flex justify-between">
          <span class="text-slate-500">Minimum Words</span>
          <span>600</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Minimum Sections</span>
          <span>4</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Active Voice Ratio</span>
          <span>≥ 55%</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Attribution Count</span>
          <span>≥ 3</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Hook Quality</span>
          <span>Required</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin' })
</script>
