<template>
  <div class="h-screen bg-slate-50 flex overflow-hidden">
    <!-- Sidebar -->
    <aside
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
      class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-slate-300 transition-transform duration-200 lg:static lg:translate-x-0 lg:h-screen flex flex-col overflow-hidden"
    >
      <!-- Logo -->
      <div class="h-14 flex items-center px-5 border-b border-slate-800 shrink-0">
        <span class="text-white font-display text-lg font-bold tracking-tight">Admin</span>
        <span class="ml-2 text-[10px] uppercase tracking-[0.2em] text-slate-500">Panel</span>
      </div>

      <!-- Nav -->
      <nav class="flex-1 overflow-y-auto py-3 px-3 space-y-0.5">
        <template v-for="group in navGroups" :key="group.label">
          <div class="px-2 pt-4 pb-1">
            <span class="text-[10px] uppercase tracking-[0.15em] text-slate-500 font-semibold">{{ group.label }}</span>
          </div>
          <NuxtLink
            v-for="link in group.items"
            :key="link.to"
            :to="link.to"
            :class="isActive(link.to) ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800/50'"
            class="flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors"
          >
            <component :is="link.icon" class="w-4 h-4 shrink-0" />
            <span>{{ link.label }}</span>
            <span v-if="link.badge" class="ml-auto text-[10px] bg-red-500 text-white px-1.5 py-0.5 rounded-full">{{ link.badge }}</span>
          </NuxtLink>
        </template>
      </nav>

      <!-- User -->
      <div class="border-t border-slate-800 p-3 shrink-0">
        <div class="flex items-center gap-3 px-3 py-2 rounded-md">
          <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-white">
            {{ userInitials }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white truncate">{{ adminUser?.name || 'Admin' }}</p>
            <p class="text-xs text-slate-500 truncate">{{ adminUser?.email || '' }}</p>
          </div>
          <button @click="handleLogout" class="text-slate-500 hover:text-white transition-colors" title="Sign out">
            <LogOut class="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>

    <!-- Mobile overlay -->
    <div v-if="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-black/50 lg:hidden" />

    <!-- Main content -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Top bar -->
      <header class="h-14 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-6 sticky top-0 z-30">
        <div class="flex items-center gap-3">
          <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-slate-500 hover:text-slate-900">
            <Menu class="w-5 h-5" />
          </button>
          <h1 class="font-display text-lg font-bold text-slate-900">{{ pageTitle }}</h1>
        </div>
        <div class="flex items-center gap-4">
          <NuxtLink to="/" target="_blank" class="text-xs text-slate-500 hover:text-slate-900 flex items-center gap-1.5">
            <ExternalLink class="w-3.5 h-3.5" />
            View Site
          </NuxtLink>
        </div>
      </header>

      <!-- Content -->
      <main class="flex-1 p-4 lg:p-6 overflow-y-auto">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Menu, LayoutDashboard, FileText, FolderOpen, Tag, Users, ScrollText, Settings, Search, Zap, BarChart3, LogOut, ExternalLink, Activity } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const { logout, user: adminUser } = useAdminAuth()
const sidebarOpen = ref(false)

const userInitials = computed(() => {
  const name = adminUser.value?.name || 'A'
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

const navGroups = [
  {
    label: 'Overview',
    items: [
      { to: '/admin', label: 'Dashboard', icon: LayoutDashboard },
      { to: '/admin/articles', label: 'Articles', icon: FileText },
      { to: '/admin/topics', label: 'Topics', icon: FolderOpen },
      { to: '/admin/categories', label: 'Categories', icon: Tag },
    ]
  },
  {
    label: 'Operations',
    items: [
      { to: '/admin/discovery', label: 'Discovery', icon: Search },
      { to: '/admin/generation', label: 'Generation', icon: Zap },
      { to: '/admin/queue', label: 'Queue Monitor', icon: Activity, badge: undefined },
      { to: '/admin/audit', label: 'Audit Log', icon: ScrollText },
    ]
  },
  {
    label: 'Administration',
    items: [
      { to: '/admin/users', label: 'Users', icon: Users },
      { to: '/admin/settings', label: 'Settings', icon: Settings },
    ]
  },
]

const pageTitles: Record<string, string> = {
  '/admin': 'Dashboard',
  '/admin/articles': 'Articles',
  '/admin/topics': 'Topics',
  '/admin/categories': 'Categories',
  '/admin/discovery': 'Discovery',
  '/admin/generation': 'Generation',
  '/admin/queue': 'Queue Monitor',
  '/admin/audit': 'Audit Log',
  '/admin/users': 'Users',
  '/admin/settings': 'Settings',
}

const pageTitle = computed(() => {
  for (const [path, title] of Object.entries(pageTitles)) {
    if (route.path === path || route.path.startsWith(path + '/')) return title
  }
  return 'Admin'
})

function isActive(path: string) {
  if (path === '/admin') return route.path === '/admin'
  return route.path.startsWith(path)
}

async function handleLogout() {
  await logout()
  router.push('/admin/login')
}
</script>