<template>
  <div class="space-y-4">
    <div class="bg-white rounded-lg border border-slate-200 p-3 flex flex-wrap items-center gap-3">
      <select v-model="filters.action" class="border border-slate-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500"
      >
        <option value="">All Actions</option>
        <option value="login">Login</option>
        <option value="logout">Logout</option>
        <option value="create">Create</option>
        <option value="update">Update</option>
        <option value="delete">Delete</option>
      </select>
      <span class="text-xs text-slate-500">{{ logs.meta?.total || 0 }} entries</span>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500 text-xs uppercase">Time</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500 text-xs uppercase">User</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500 text-xs uppercase">Action</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500 text-xs uppercase">Resource</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500 text-xs uppercase">IP</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="l in logs.data" :key="l.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 text-xs text-slate-400">{{ formatDate(l.created_at) }}</td>
            <td class="px-4 py-3 text-xs">{{ l.user_name }}</td>
            <td class="px-4 py-3">
              <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="actionClass(l.action)"
              >{{ l.action }}</span>
            </td>
            <td class="px-4 py-3 text-slate-500">{{ l.resource_type }}{{ l.resource_id ? '#' + l.resource_id : '' }}</td>
            <td class="px-4 py-3 text-xs text-slate-400">{{ l.ip_address }}</td>
          </tr>
          <tr v-if="!logs.data?.length">
            <td colspan="5" class="px-4 py-8 text-center text-slate-400">No audit logs</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const logs = ref<any>({ data: [], meta: null })
const filters = reactive({ action: '' })

watch(() => filters.action, loadLogs)

function actionClass(action: string) {
  if (action === 'login') return 'bg-emerald-100 text-emerald-700'
  if (action === 'delete') return 'bg-red-100 text-red-700'
  if (action === 'create') return 'bg-blue-100 text-blue-700'
  if (action === 'update') return 'bg-amber-100 text-amber-700'
  return 'bg-slate-100 text-slate-600'
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