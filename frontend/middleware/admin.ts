export default defineNuxtRouteMiddleware((to) => {
  const tokenCookie = useCookie<string | null>('admin-token', {
    path: '/',
    sameSite: 'strict',
    secure: true,
    maxAge: 86400,
  })

  // Unauthenticated: redirect to login
  if (to.path.startsWith('/admin') && to.path !== '/admin/login') {
    let token = tokenCookie.value

    // Fall back to localStorage on client (for migration from old sessions)
    if (!token && import.meta.client) {
      token = localStorage.getItem('admin-token')
      if (token) {
        // Migrate to cookie
        tokenCookie.value = token
      }
    }

    if (!token) {
      return navigateTo(`/admin/login?redirect=${encodeURIComponent(to.fullPath)}`)
    }
  }

  // Already authenticated and visiting login: go to dashboard
  if (to.path === '/admin/login') {
    let token = tokenCookie.value
    if (!token && import.meta.client) {
      token = localStorage.getItem('admin-token')
    }
    if (token) {
      return navigateTo('/admin')
    }
  }
})