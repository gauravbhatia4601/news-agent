export default defineNuxtRouteMiddleware(async (to) => {
  const tokenCookie = useCookie<string | null>('admin-token', {
    path: '/',
    sameSite: 'strict',
    secure: true,
    maxAge: 86400,
  })
  const userCookie = useCookie<string | null>('admin-user', {
    path: '/',
    sameSite: 'strict',
    secure: true,
    maxAge: 86400,
  })

  const clearAuth = () => {
    tokenCookie.value = null
    userCookie.value = null
    if (import.meta.client) {
      localStorage.removeItem('admin-token')
      localStorage.removeItem('admin-user')
    }
  }

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

    // Token present: validate it server-side so a stale/forged cookie cannot grant access.
    try {
      const res = await $fetch<{ data: any }>('/api/v1/admin/auth/me', {
        headers: { Authorization: `Bearer ${token}` },
      })
      // Overwrite the stale client-set user cookie with the server-verified identity.
      if (res?.data) userCookie.value = JSON.stringify(res.data)
    } catch {
      clearAuth()
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