<template>
  <div class="space-y-6">
    <!-- Toolbar -->
    <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-4 flex flex-wrap items-center gap-3">
      <select v-model="filters.action" class="bg-slate-50 border border-slate-200 rounded-[14px] px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-slate-400 transition-colors">
        <option value="">All Actions</option>
        <option value="login">Login</option>
        <option value="logout">Logout</option>
        <option value="create">Create</option>
        <option value="update">Update</option>
        <option value="delete">Delete</option>
      </select>
      <span class="text-xs text-slate-500 font-medium">{{ logs.meta?.total || 0 }} entries</span>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50/80 border-b border-slate-200">
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Time</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">User</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Action</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Resource</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">IP</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="l in logs.data" :key="l.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-3.5 text-xs text-slate-500 whitespace-nowrap">{{ formatDate(l.created_at) }}</td>
            <td class="px-6 py-3.5 text-sm font-medium text-slate-900">{{ l.user_name }}</td>
            <td class="px-6 py-3.5">
              <span class="text-[11px] font-medium px-2.5 py-1 rounded-full" :class="actionClass(l.action)">{{ l.action }}</span>
            </td>
            <td class="px-6 py-3.5 text-slate-500">{{ l.resource_type }}{{ l.resource_id ? '#' + l.resource_id : '' }}</td>
            <td class="px-6 py-3.5 text-xs text-slate-500">{{ l.ip_address }}</td>
          </tr>
          <tr v-if="!logs.data?.length"><td colspan="5" class="px-6 py-12 text-center text-slate-500">
            <ScrollText class="w-8 h-8 mx-auto mb-3 text-slate-400" />
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
  if (action === 'login') return 'bg-emerald-50 text-emerald-700 border border-emerald-100'
  if (action === 'delete') return 'bg-red-50 text-red-700 border border-red-100'
  if (action === 'create') return 'bg-blue-50 text-blue-700 border border-blue-100'
  if (action === 'update') return 'bg-amber-50 text-amber-700 border border-amber-100'
  if (action === 'logout') return 'bg-slate-100 text-slate-600 border border-slate-200'
  return 'bg-slate-100 text-slate-600 border border-slate-200'
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
