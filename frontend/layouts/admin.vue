<script setup lang="ts">import {
  Menu, LayoutDashboard, FileText, FolderOpen, Tag, Users, ScrollText, Settings, Search, Zap, LogOut, ExternalLink, Activity, ChevronRight
} from 'lucide-vue-next'

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
      { to: '/admin/queue', label: 'Queue Monitor', icon: Activity },
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

<template>
  <div class="h-screen flex overflow-hidden bg-[#0a0e1a]">
    <!-- Sidebar -->
    <aside
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
      class="fixed inset-y-0 left-0 z-50 w-[260px] bg-[#0a0e1a] border-r border-white/[0.06] text-[#94A3B8] transition-transform duration-300 ease-out lg:static lg:translate-x-0 flex flex-col"
    >
      <!-- Logo -->
      <div class="h-16 flex items-center px-6 border-b border-white/[0.06] shrink-0">
        <NuxtLink to="/" target="_blank" class="flex items-center gap-3 group">
          <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" class="shrink-0">
            <rect width="28" height="28" rx="8" fill="#1a2233" />
            <path d="M10 7L10 21M10 7L16 10.5M10 7L16 3.5" stroke="#f5a623" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M13.5 9.5C14.5 11 16 12.5 18 13M18 13L15.5 10.5M18 13L16.5 15.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="19.5" cy="18.5" r="2" stroke="white" stroke-width="1" />
          </svg>
          <div>
            <span class="text-white font-semibold text-[14px] tracking-tight leading-none">The AI Journal</span>
            <span class="block text-[10px] text-[#64748B] uppercase tracking-[0.15em] font-medium leading-tight mt-0.5">Admin</span>
          </div>
        </NuxtLink>
      </div>

      <!-- Nav -->
      <nav class="flex-1 overflow-y-auto py-5 px-3 space-y-6">
        <template v-for="group in navGroups" :key="group.label">
          <div>
            <div class="px-3 mb-2"><span class="text-[10px] uppercase tracking-[0.15em] text-[#475569] font-semibold">{{ group.label }}</span></div>
            <div class="space-y-0.5">
              <NuxtLink
                v-for="link in group.items"
                :key="link.to"
                :to="link.to"
                class="flex items-center gap-3 px-3 py-2.5 rounded-[14px] text-sm transition-all duration-200 relative"
                :class="isActive(link.to)
                  ? 'bg-[#1a2233] text-white font-medium shadow-[0_0_0_1px_rgba(245,166,35,0.15)]'
                  : 'text-[#94A3B8] hover:text-white hover:bg-white/[0.04]'"
              >
                <component :is="link.icon" class="w-[18px] h-[18px] shrink-0" stroke-width="1.75" />
                <span>{{ link.label }}</span>
                <ChevronRight v-if="isActive(link.to)" class="w-3 h-3 ml-auto text-[#f5a623]" stroke-width="2" />
              </NuxtLink>
            </div>
          </div>
        </template>
      </nav>

      <!-- User -->
      <div class="border-t border-white/[0.06] p-3 shrink-0">
        <div class="flex items-center gap-3 px-3 py-2.5 rounded-[14px] hover:bg-white/[0.04] transition-colors cursor-pointer group">
          <div class="w-9 h-9 rounded-full bg-[#1a2233] border border-[#f5a623]/20 flex items-center justify-center text-[11px] font-bold text-white font-mono tracking-tight">
            {{ userInitials }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white">{{ adminUser?.name || 'Admin' }}</p>
            <p class="text-[11px] text-[#64748B] truncate">{{ adminUser?.email || '' }}</p>
          </div>
          <button @click="handleLogout" class="opacity-0 group-hover:opacity-100 text-[#64748B] hover:text-white transition-all p-1.5 rounded-lg hover:bg-white/[0.08]"
            title="Sign out"
          >
            <LogOut class="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>

    <!-- Overlay -->
    <div v-if="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden" />

    <!-- Main area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <header class="h-16 bg-[#0a0e1a]/80 backdrop-blur-xl border-b border-white/[0.06] flex items-center justify-between px-6 lg:px-8 sticky top-0 z-30">
        <div class="flex items-center gap-4">
          <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 -ml-2 rounded-[14px] hover:bg-white/[0.06] transition-colors text-[#94A3B8]"
          >
            <Menu class="w-5 h-5" />
          </button>
          <div class="hidden lg:flex items-center gap-2 text-xs text-[#64748B] font-mono">
            <span>Admin</span>
            <span class="text-[#475569]">/</span>
            <span class="text-white font-medium">{{ pageTitle }}</span>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <NuxtLink to="/" target="_blank"
            class="flex items-center gap-1.5 text-xs text-[#94A3B8] hover:text-white transition-colors px-3 py-1.5 rounded-[14px] hover:bg-white/[0.04]"
          >
            <ExternalLink class="w-3.5 h-3.5" stroke-width="1.75" />
            View Site
          </NuxtLink>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto">
        <div class="p-6 lg:p-8">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<style>
main { scroll-behavior: smooth; }
aside::-webkit-scrollbar { width: 0px; }
aside:hover::-webkit-scrollbar { width: 4px; }
aside::-webkit-scrollbar-track { background: transparent; }
aside::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 4px; }
</style>