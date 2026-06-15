<template>
  <div class="space-y-4">
    <div class="bg-white rounded-lg border border-slate-200 p-3 flex items-center justify-between">
      <input v-model="filters.search" type="search" placeholder="Search users..."
        class="border border-slate-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500 w-64" />
      <button @click="showAdd = true"
        class="bg-blue-600 text-white px-3 py-1.5 rounded-md text-sm hover:bg-blue-700 transition-colors"
      >
        Add User
      </button>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500 text-xs uppercase">Name</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500 text-xs uppercase">Email</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500 text-xs uppercase">Roles</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500 text-xs uppercase">Created</th>
            <th class="px-4 py-2.5 text-right font-medium text-slate-500 text-xs uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="u in users.data" :key="u.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium">{{ u.name }}</td>
            <td class="px-4 py-3 text-slate-500">{{ u.email }}</td>
            <td class="px-4 py-3">
              <span v-for="r in u.roles" :key="r.id"
                class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 mr-1"
              >{{ r.name }}</span>
              <span v-if="u.is_admin" class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Admin</span>
            </td>
            <td class="px-4 py-3 text-xs text-slate-400">{{ formatDate(u.created_at) }}</td>
            <td class="px-4 py-3 text-right">
              <button @click="deleteUser(u.id)" class="text-slate-400 hover:text-red-600">
                <Trash2 class="w-3.5 h-3.5 inline" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add user modal -->
    <div v-if="showAdd" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showAdd = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h2 class="font-semibold mb-4">Add User</h2>
        <form @submit.prevent="createUser" class="space-y-4">
          <input v-model="newUser.name" placeholder="Name" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" />
          <input v-model="newUser.email" type="email" placeholder="Email" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" />
          <input v-model="newUser.password" type="password" placeholder="Password" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm" />
          <select v-model="newUser.role_ids" multiple class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"
          >
            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
          </select>
          <div class="flex gap-2 justify-end">
            <button type="button" @click="showAdd = false" class="px-4 py-2 text-sm border border-slate-300 rounded-md">Cancel</button>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-md">Create</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next'

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
    await api.authFetch('/users', {
      method: 'POST',
      body: {
        name: newUser.name,
        email: newUser.email,
        password: newUser.password,
        role_ids: newUser.role_ids,
      },
    })
    showAdd.value = false
    newUser.name = ''; newUser.email = ''; newUser.password = ''; newUser.role_ids = []
    await loadUsers()
  } catch {}
}

async function deleteUser(id: number) {
  if (!confirm('Delete this user?')) return
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