export default defineNuxtPlugin(() => {
  if (process.client) {
    const storedToken = localStorage.getItem('admin-token')
    const storedUser = localStorage.getItem('admin-user')

    if (storedToken) {
      useState('admin-token', () => storedToken)
      if (storedUser) {
        try {
          const user = JSON.parse(storedUser)
          useState('admin-user', () => user)
        } catch {}
      }

      // Migrate to secure cookie and clean up localStorage
      const tokenCookie = useCookie<string>('admin-token', {
        path: '/',
        sameSite: 'strict',
        secure: true,
        maxAge: 86400,
      })
      if (!tokenCookie.value) {
        tokenCookie.value = storedToken
        if (storedUser) {
          const userCookie = useCookie<string>('admin-user', {
            path: '/',
            sameSite: 'strict',
            secure: true,
            maxAge: 86400,
          })
          userCookie.value = storedUser
        }
      }

      // Remove from localStorage — token is now cookie-only
      localStorage.removeItem('admin-token')
      localStorage.removeItem('admin-user')
    }
  }
})