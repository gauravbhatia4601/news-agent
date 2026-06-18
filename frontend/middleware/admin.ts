export default defineNuxtRouteMiddleware((to) => {
  if (to.path.startsWith('/admin') && to.path !== '/admin/login') {
    let token: string | null = null

    if (import.meta.server) {
      token = useCookie<string | null>('admin-token', { path: '/', sameSite: 'lax' }).value || null
    } else {
      token = localStorage.getItem('admin-token')
    }

    if (!token) {
      return navigateTo(`/admin/login?redirect=${encodeURIComponent(to.fullPath)}`)
    }
  }

  // If already authenticated and visiting login, go to dashboard
  if (to.path === '/admin/login') {
    let token: string | null = null
    if (import.meta.server) {
      token = useCookie<string | null>('admin-token', { path: '/', sameSite: 'lax' }).value || null
    } else {
      token = localStorage.getItem('admin-token')
    }
    if (token) {
      return navigateTo('/admin')
    }
  }
})