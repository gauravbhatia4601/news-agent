export default defineNuxtPlugin(() => {
  if (process.client) {
    const storedToken = localStorage.getItem('admin-token')
    const storedUser = localStorage.getItem('admin-user')
    if (storedToken) {
      useState('admin-token', () => storedToken)
    }
    if (storedUser) {
      try {
        const user = JSON.parse(storedUser)
        useState('admin-user', () => user)
      } catch {}
    }
  }
})
