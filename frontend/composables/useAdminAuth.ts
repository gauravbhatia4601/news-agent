export const useAdminAuth = () => {
  const token = useState<string | null>('admin-token', () => null)
  const user = useState<{ id: number; name: string; email: string; is_admin: boolean } | null>('admin-user', () => null)
  const isAuthenticated = computed(() => !!token.value)

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

  if (!token.value) {
    if (import.meta.server) {
      if (tokenCookie.value) {
        token.value = tokenCookie.value
        if (userCookie.value) {
          try { user.value = JSON.parse(userCookie.value as string) } catch {}
        }
      }
    } else {
      const storedToken = localStorage.getItem('admin-token')
      const storedUser = localStorage.getItem('admin-user')
      if (storedToken) {
        token.value = storedToken
        // Migrate to cookie if not already there
        if (!tokenCookie.value) {
          tokenCookie.value = storedToken
          if (storedUser) tokenCookie.value = storedUser
        }
      }
      if (storedUser) { try { user.value = JSON.parse(storedUser) } catch {} }
    }
  }

  const login = async (email: string, password: string) => {
    const res = await $fetch<{ data: { user: any; token: string } }>('/api/v1/admin/auth/login', {
      method: 'POST',
      body: { email, password },
    })

    token.value = res.data.token
    user.value = res.data.user

    if (import.meta.client) {
      localStorage.removeItem('admin-token')
      localStorage.removeItem('admin-user')
      tokenCookie.value = res.data.token
      userCookie.value = JSON.stringify(res.data.user)
    }

    return res.data
  }

  const logout = async () => {
    try {
      await $fetch('/api/v1/admin/auth/logout', {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}` },
      })
    } catch {}

    token.value = null
    user.value = null

    if (import.meta.client) {
      localStorage.removeItem('admin-token')
      localStorage.removeItem('admin-user')
      tokenCookie.value = null
      userCookie.value = null
    }
  }

  const fetchUser = async () => {
    if (!token.value) return null
    try {
      const res = await $fetch<{ data: any }>('/api/v1/admin/auth/me', {
        headers: { Authorization: `Bearer ${token.value}` },
      })
      user.value = res.data
      return res.data
    } catch {
      token.value = null
      user.value = null
      if (import.meta.client) {
        localStorage.removeItem('admin-token')
        localStorage.removeItem('admin-user')
        tokenCookie.value = null
        userCookie.value = null
      }
      return null
    }
  }

  return { token, user, isAuthenticated, login, logout, fetchUser }
}