<template>
  <div class="space-y-6">
    <!-- Toolbar -->
    <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] p-4 flex flex-wrap items-center gap-3">
      <select v-model="filters.action" class="bg-[#0a0e1a] border border-white/[0.08] rounded-[14px] px-4 py-2.5 text-sm text-white focus:outline-none focus:border-[#f5a623]/40 transition-colors">
        <option value="">All Actions</option>
        <option value="login">Login</option>
        <option value="logout">Logout</option>
        <option value="create">Create</option>
        <option value="update">Update</option>
        <option value="delete">Delete</option>
      </select>
      <span class="text-xs text-[#64748B] font-medium font-mono">{{ logs.meta?.total || 0 }} entries</span>
    </div>

    <!-- Table -->
    <div class="bg-[#111827] rounded-[14px] border border-white/[0.06] overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-white/[0.04] border-b border-white/[0.06]">
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">Time</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">User</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">Action</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">Resource</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-[#94A3B8] uppercase tracking-wider font-mono">IP</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/[0.04]">
          <tr v-for="l in logs.data" :key="l.id" class="hover:bg-white/[0.02] transition-colors">
            <td class="px-6 py-3.5 text-xs text-[#64748B] whitespace-nowrap font-mono">{{ formatDate(l.created_at) }}</td>
            <td class="px-6 py-3.5 text-sm font-medium text-white">{{ l.user_name }}</td>
            <td class="px-6 py-3.5">
              <span class="text-[11px] font-medium px-2.5 py-1 rounded-full" :class="actionClass(l.action)">{{ l.action }}</span>
            </td>
            <td class="px-6 py-3.5 text-[#94A3B8]">{{ l.resource_type }}{{ l.resource_id ? '#' + l.resource_id : '' }}</td>
            <td class="px-6 py-3.5 text-xs text-[#64748B] font-mono">{{ l.ip_address }}</td>
          </tr>
          <tr v-if="!logs.data?.length"><td colspan="5" class="px-6 py-12 text-center text-[#94A3B8]">
            <ScrollText class="w-8 h-8 mx-auto mb-3 text-[#475569]" />
            No audit logs
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ScrollText } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const logs = ref<any>({ data: [], meta: null })
const filters = reactive({ action: '' })

watch(() => filters.action, loadLogs)

function actionClass(action: string) {
  if (action === 'login') return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
  if (action === 'delete') return 'bg-red-500/10 text-red-400 border border-red-500/20'
  if (action === 'create') return 'bg-blue-500/10 text-blue-400 border border-blue-500/20'
  if (action === 'update') return 'bg-amber-400/10 text-amber-400 border border-amber-400/20'
  if (action === 'logout') return 'bg-white/[0.06] text-[#94A3B8] border border-white/[0.08]'
  return 'bg-white/[0.06] text-[#94A3B8] border border-white/[0.08]'
}

function formatDate(d: string) {
  return new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function loadLogs() {
  try {
    const params: any = {}
    if (filters.action) params.action = filters.action
    logs.value = await api.authFetch<any>('/audit', { query: params })
  } catch {}
}

onMounted(loadLogs)
</script>
