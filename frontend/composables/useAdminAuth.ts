export const useAdminAuth = () => {
  const token = useState<string | null>('admin-token', () => null)
  const user = useState<{ id: number; name: string; email: string; is_admin: boolean } | null>('admin-user', () => null)
  const isAuthenticated = computed(() => !!token.value)

  const config = useRuntimeConfig()
  const backendBase = String(config.backendApiBase || 'http://127.0.0.1:8001')

  // Eagerly restore from localStorage on both client setup and SSR plugin init
  if (import.meta.client && !token.value) {
    const storedToken = localStorage.getItem('admin-token')
    const storedUser = localStorage.getItem('admin-user')
    if (storedToken) {
      token.value = storedToken
    }
    if (storedUser) {
      try { user.value = JSON.parse(storedUser) } catch {}
    }
  }

  const login = async (email: string, password: string) => {
    const res = await $fetch<{ data: { user: any; token: string } }>(`${backendBase}/api/v1/admin/auth/login`, {
      method: 'POST',
      body: { email, password },
    })

    token.value = res.data.token
    user.value = res.data.user

    if (import.meta.client) {
      localStorage.setItem('admin-token', res.data.token)
      localStorage.setItem('admin-user', JSON.stringify(res.data.user))
    }

    return res.data
  }

  const logout = async () => {
    try {
      await $fetch(`${backendBase}/api/v1/admin/auth/logout`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}` },
      })
    } catch {}

    token.value = null
    user.value = null

    if (import.meta.client) {
      localStorage.removeItem('admin-token')
      localStorage.removeItem('admin-user')
    }
  }

  const fetchUser = async () => {
    if (!token.value) return null
    try {
      const res = await $fetch<{ data: any }>(`${backendBase}/api/v1/admin/auth/me`, {
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
      }
      return null
    }
  }

  const initFromStorage = () => {
    if (import.meta.client && !token.value) {
      const storedToken = localStorage.getItem('admin-token')
      const storedUser = localStorage.getItem('admin-user')
      if (storedToken) {
        token.value = storedToken
      }
      if (storedUser) {
        try { user.value = JSON.parse(storedUser) } catch {}
      }
    }
  }

  return { token, user, isAuthenticated, login, logout, fetchUser, initFromStorage }
}
