export default defineNuxtRouteMiddleware((to) => {
  if (to.path.startsWith('/admin') && to.path !== '/admin/login') {
    if (import.meta.client) {
      const token = localStorage.getItem('admin-token')
      if (!token) {
        return navigateTo('/admin/login')
      }
    }
  }
})