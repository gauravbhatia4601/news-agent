export default defineNuxtPlugin(() => {
  if (process.client) {
    const storedToken = localStorage.getItem('admin-token')
    const storedUser = localStorage.getItem('admin-user')
    if (storedToken) {
      useState('admin-token', () => storedToken)
      // Backfill cookie if missing (migration from localStorage-only)
      const tokenCookie = useCookie<string>('admin-token', { path: '/', sameSite: 'lax', maxAge: 604800 })
      if (!tokenCookie.value) {
        tokenCookie.value = storedToken
        if (storedUser) {
          const userCookie = useCookie<string>('admin-user', { path: '/', sameSite: 'lax', maxAge: 604800 })
          userCookie.value = storedUser
        }
      }
    }
    if (storedUser) {
      try {
        const user = JSON.parse(storedUser)
        useState('admin-user', () => user)
      } catch {}
    }
  }
})