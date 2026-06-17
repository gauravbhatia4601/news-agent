<template>
  <div class="space-y-6">
    <!-- Toolbar -->
    <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-4 flex items-center justify-between">
      <input v-model="filters.search" type="search" placeholder="Search users..."
        class="bg-slate-50 border border-slate-200 rounded-[14px] px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-slate-400 transition-colors w-72" />
      <button @click="showAdd = true"
        class="bg-slate-900 hover:bg-slate-800 text-white px-4 py-2.5 rounded-[14px] text-sm font-medium transition-all flex items-center gap-2"
      >
        <Plus class="w-4 h-4" />
        Add User
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-[14px] border border-slate-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50/80 border-b border-slate-200">
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Name</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Email</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Roles</th>
            <th class="px-6 py-3.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Created</th>
            <th class="px-6 py-3.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="u in users.data" :key="u.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-3.5">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-slate-900 text-white flex items-center justify-center text-xs font-bold">
                  {{ (u.name || 'U').charAt(0).toUpperCase() }}
                </div>
                <span class="font-medium text-slate-900">{{ u.name }}</span>
              </div>
            </td>
            <td class="px-6 py-3.5 text-slate-500">{{ u.email }}</td>
            <td class="px-6 py-3.5">
              <span v-for="r in u.roles" :key="r.id" class="text-[11px] px-2 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200 mr-1">{{ r.name }}</span>
              <span v-if="u.is_admin" class="text-[11px] px-2 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">Admin</span>
            </td>
            <td class="px-6 py-3.5 text-xs text-slate-500">{{ formatDate(u.created_at) }}</td>
            <td class="px-6 py-3.5 text-right">
              <button @click="deleteUser(u.id)" class="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </td>
          </tr>
          <tr v-if="!users.data?.length"><td colspan="5" class="px-6 py-12 text-center text-slate-500">
            <Users class="w-8 h-8 mx-auto mb-3 text-slate-400" />
            No users found
          </td></tr>
        </tbody>
      </table>
    </div>

    <!-- Add user modal -->
    <div v-if="showAdd" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showAdd = false">
      <div class="bg-white rounded-[14px] shadow-2xl w-full max-w-md p-6 border border-slate-200">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-slate-900/5 flex items-center justify-center"><Plus class="w-5 h-5 text-amber-600" /></div>
          <h2 class="text-[18px] font-semibold text-slate-900">Add User</h2>
        </div>

        <form @submit.prevent="createUser" class="space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Name</label>
            <input v-model="newUser.name" placeholder="Full name" required class="w-full bg-slate-50 border border-slate-200 rounded-[14px] px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-slate-400 transition-colors" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Email</label>
            <input v-model="newUser.email" type="email" placeholder="email@example.com" required class="w-full bg-slate-50 border border-slate-200 rounded-[14px] px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-slate-400 transition-colors" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Password</label>
            <input v-model="newUser.password" type="password" placeholder="Secure password" required class="w-full bg-slate-50 border border-slate-200 rounded-[14px] px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-slate-400 transition-colors" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Roles</label>
            <select v-model="newUser.role_ids" multiple class="w-full bg-slate-50 border border-slate-200 rounded-[14px] px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-slate-400 transition-colors"
            >
              <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
            </select>
          </div>
          <div class="flex gap-2 justify-end pt-2">
            <button type="button" @click="showAdd = false" class="px-4 py-2.5 text-sm border border-slate-200 rounded-[14px] hover:bg-slate-50 text-slate-600 transition-colors">Cancel</button>
            <button type="submit" class="px-4 py-2.5 text-sm bg-slate-900 text-white rounded-[14px] font-medium hover:bg-slate-800 transition-colors">Create</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Trash2, Plus, Users } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const users = ref<any>({ data: [], meta: null })
const roles = ref<any[]>([])
const showAdd = ref(false)
const filters = reactive({ search: '' })
const newUser = reactive({ name: '', email: '', password: '', role_ids: [] as number[] })

watch(() => filters.search, loadUsers)

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

async function loadUsers() {
  try {
    const params: any = { page: 1 }
    if (filters.search) params.search = filters.search
    users.value = await api.authFetch<any>('/users', { query: params })
  } catch {}
}

async function loadRoles() {
  try {
    const res = await api.authFetch<{ data: any[] }>('/users/roles')
    roles.value = res.data
  } catch {}
}

async function createUser() {
  try {
    await api.authFetch('/users', { method: 'POST', body: { ...newUser } })
    newUser.name = ''
    newUser.email = ''
    newUser.password = ''
    newUser.role_ids = []
    showAdd.value = false
    await loadUsers()
  } catch {}
}

async function deleteUser(id: number) {
  if (!confirm('Are you sure you want to delete this user?')) return
  try {
    await api.authFetch(`/users/${id}`, { method: 'DELETE' })
    await loadUsers()
  } catch {}
}

onMounted(() => {
  loadUsers()
  loadRoles()
})
</script>
