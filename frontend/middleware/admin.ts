export default defineNuxtRouteMiddleware((to) => {
  if (to.path.startsWith('/admin') && to.path !== '/admin/login') {
    let token: string | null = null

    if (import.meta.server) {
      const cookie = useCookie('admin-token').value
      token = cookie || null
    } else {
      token = localStorage.getItem('admin-token')
    }

    if (!token) {
      return navigateTo('/admin/login')
    }
  }
})