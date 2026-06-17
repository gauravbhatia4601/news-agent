<template>
  <div class="space-y-5">
    <!-- Toolbar -->
    <div class="bg-white rounded-2xl border border-gray-200/60 p-4 flex flex-wrap items-center gap-3">
      <select v-model="filters.action" class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 bg-white focus:outline-none focus:border-[#1a2233] transition-colors">
        <option value="">All Actions</option>
        <option value="login">Login</option>
        <option value="logout">Logout</option>
        <option value="create">Create</option>
        <option value="update">Update</option>
        <option value="delete">Delete</option>
      </select>
      <span class="text-xs text-gray-400 font-medium">{{ logs.meta?.total || 0 }} entries</span>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-200/60 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50/80 border-b border-gray-100">
            <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Time</th>
            <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">User</th>
            <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Action</th>
            <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Resource</th>
            <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">IP</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="l in logs.data" :key="l.id" class="hover:bg-gray-50/50 transition-colors">
            <td class="px-6 py-3 text-xs text-gray-400 whitespace-nowrap">{{ formatDate(l.created_at) }}</td>
            <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ l.user_name }}</td>
            <td class="px-6 py-3">
              <span class="text-[11px] font-medium px-2.5 py-1 rounded-full" :class="actionClass(l.action)">{{ l.action }}</span>
            </td>
            <td class="px-6 py-3 text-gray-500">{{ l.resource_type }}{{ l.resource_id ? '#' + l.resource_id : '' }}</td>
            <td class="px-6 py-3 text-xs text-gray-400 font-mono">{{ l.ip_address }}</td>
          </tr>
          <tr v-if="!logs.data?.length"><td colspan="5" class="px-6 py-12 text-center text-gray-400">
            <ScrollText class="w-8 h-8 mx-auto mb-3 text-gray-200" />
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
  if (action === 'logout') return 'bg-gray-50 text-gray-600 border border-gray-100'
  return 'bg-gray-50 text-gray-600 border border-gray-100'
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