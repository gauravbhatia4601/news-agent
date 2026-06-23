import type { UseFetchOptions } from 'nuxt/app'

export const useAdminApi = () => {
  const { token } = useAdminAuth()

  const headers = computed(() => ({
    'Authorization': `Bearer ${token.value}`,
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  }))

  const authFetch = <T>(url: string, options: any = {}) => {
    return $fetch<T>(`/api/v1/admin${url}`, {
      ...options,
      headers: { ...headers.value, ...(options.headers || {}) },
    })
  }

  return {
    authFetch,

    getDashboard: () => authFetch<{ data: any }>('/dashboard'),

    getArticles: (params: Record<string, string | number> = {}) => {
      const query = new URLSearchParams(Object.entries(params).map(([k, v]) => [k, String(v)])).toString()
      return authFetch<any>(`/articles${query ? '?' + query : ''}`)
    },
    getArticle: (id: number) => authFetch<{ data: any }>(`/articles/${id}`),
    updateArticle: (id: number, data: any) => authFetch<{ data: any }>(`/articles/${id}`, { method: 'PUT', body: data }),
    deleteArticle: (id: number) => authFetch<any>(`/articles/${id}`, { method: 'DELETE' }),
    regenerateArticle: (id: number) => authFetch<any>(`/articles/${id}/regenerate`, { method: 'POST' }),
    batchArticles: (ids: number[], action: string) => authFetch<any>('/articles/batch', { method: 'POST', body: { ids, action } }),

    getTopics: (params: Record<string, string | number> = {}) => {
      const query = new URLSearchParams(Object.entries(params).map(([k, v]) => [k, String(v)])).toString()
      return authFetch<any>(`/topics${query ? '?' + query : ''}`)
    },
    getTopic: (id: number) => authFetch<{ data: any }>(`/topics/${id}`),
    retryTopic: (id: number) => authFetch<any>(`/topics/${id}/retry`, { method: 'POST' }),
    dispatchTopic: (id: number) => authFetch<any>(`/topics/${id}/dispatch`, { method: 'POST' }),
    deleteTopic: (id: number) => authFetch<any>(`/topics/${id}`, { method: 'DELETE' }),
    batchTopics: (ids: number[], action: string) => authFetch<any>('/topics/batch', { method: 'POST', body: { ids, action } }),

    getCategories: () => authFetch<{ data: any[] }>('/categories'),
    createCategory: (data: any) => authFetch<{ data: any }>('/categories', { method: 'POST', body: data }),
    updateCategory: (id: number, data: any) => authFetch<{ data: any }>(`/categories/${id}`, { method: 'PUT', body: data }),
    deleteCategory: (id: number) => authFetch<any>(`/categories/${id}`, { method: 'DELETE' }),
    reorderCategories: (orders: Array<{ id: number; display_order: number }>) => authFetch<any>('/categories/reorder', { method: 'POST', body: { orders } }),

    triggerDiscovery: (params: Record<string, number | boolean> = {}) => authFetch<{ data: any }>('/discovery/trigger', { method: 'POST', body: params }),
    retryFailed: (maxRetries = 3) => authFetch<{ data: any }>('/discovery/retry-failed', { method: 'POST', body: { max_retries: maxRetries } }),

    getQueueStatus: () => authFetch<{ data: any }>('/generation/queue'),
    getQueueHistory: (params: Record<string, string | number> = {}) => {
      const query = new URLSearchParams(Object.entries(params).map(([k, v]) => [k, String(v)])).toString()
      return authFetch<any>(`/generation/queue-history${query ? '?' + query : ''}`)
    },
    regenerateSitemap: () => authFetch<{ data: any }>('/generation/sitemap', { method: 'POST' }),
    getGenerationStats: () => authFetch<{ data: any }>('/generation/stats'),

    getSitemaps: () => authFetch<{ data: any[] }>('/generation/sitemaps'),
    getSitemap: (name: string) => authFetch<{ data: any }>(`/generation/sitemaps/${encodeURIComponent(name)}`),

    getSubscribers: (params: Record<string, string | number | undefined> = {}) => {
      const query = new URLSearchParams(
        Object.entries(params)
          .filter(([, v]) => v != null && v !== '')
          .map(([k, v]) => [k, String(v)])
      ).toString()
      return authFetch<any>(`/newsletter/subscribers${query ? '?' + query : ''}`)
    },
    deleteSubscriber: (id: number) => authFetch<any>(`/newsletter/subscribers/${id}`, { method: 'DELETE' }),

    getAiInvocations: (params: Record<string, string | number | undefined> = {}) => {
      const query = new URLSearchParams(
        Object.entries(params)
          .filter(([, v]) => v != null && v !== '')
          .map(([k, v]) => [k, String(v)])
      ).toString()
      return authFetch<any>(`/ai-invocations${query ? '?' + query : ''}`)
    },
  }
}