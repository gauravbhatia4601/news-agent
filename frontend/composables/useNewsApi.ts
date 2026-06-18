import type {
  ApiCollectionResponse,
  ApiItemResponse,
  CategoryDetail,
  CategoryNode,
  NewsArticleCard,
  NewsArticleDetail,
} from '~/types/news'

export function useNewsApi() {
  const client = $fetch.create({
    baseURL: '/api/v1',
  })

  const normalizeMediaUrl = (url?: string | null): string | null => {
    if (!url) return null

    const trimmed = String(url).trim()
    if (!trimmed) return null

    if (trimmed.startsWith('/storage/')) {
      return trimmed
    }
    if (trimmed.startsWith('storage/')) {
      return `/${trimmed}`
    }

    try {
      const parsed = new URL(trimmed)
      if (parsed.pathname.startsWith('/storage/')) {
        return parsed.pathname + parsed.search
      }
    } catch {
    }

    return trimmed
  }

  const normalizeArticleMedia = <T extends { image_url?: string | null; thumbnail_url?: string | null }>(article: T): T => {
    const imageUrl = normalizeMediaUrl(article.image_url)
    const thumbnailUrl = normalizeMediaUrl(article.thumbnail_url) || imageUrl

    return {
      ...article,
      image_url: imageUrl,
      thumbnail_url: thumbnailUrl,
    }
  }

  return {
    async getCategoryTree(): Promise<CategoryNode[]> {
      const response = await client<{ data: CategoryNode[] }>('/categories')
      return response.data ?? []
    },

    async getLatest(params: { category?: string; perPage?: number } = {}): Promise<NewsArticleCard[]> {
      const response = await client<ApiCollectionResponse<NewsArticleCard>>('/articles', {
        query: {
          category: params.category,
          per_page: params.perPage ?? 12,
        },
      })

      return (response.data ?? []).map(normalizeArticleMedia)
    },

    async getPopular(params: { category?: string; perPage?: number } = {}): Promise<NewsArticleCard[]> {
      const response = await client<ApiCollectionResponse<NewsArticleCard>>('/articles/popular', {
        query: {
          category: params.category,
          per_page: params.perPage ?? 12,
        },
      })

      return (response.data ?? []).map(normalizeArticleMedia)
    },

    async getHot(params: { category?: string; perPage?: number } = {}): Promise<NewsArticleCard[]> {
      const response = await client<ApiCollectionResponse<NewsArticleCard>>('/articles/hot', {
        query: {
          category: params.category,
          per_page: params.perPage ?? 12,
        },
      })

      return (response.data ?? []).map(normalizeArticleMedia)
    },

    async getTrending(limit = 10): Promise<NewsArticleCard[]> {
      const response = await client<ApiCollectionResponse<NewsArticleCard>>('/articles/trending', {
        query: { per_page: limit },
      })

      return (response.data ?? []).map(normalizeArticleMedia)
    },

    async getHeadlines(limit = 5): Promise<NewsArticleCard[]> {
      const response = await client<ApiCollectionResponse<NewsArticleCard>>('/articles/headlines', {
        query: { per_page: limit },
      })

      return (response.data ?? []).map(normalizeArticleMedia)
    },

    async getFeatured(): Promise<NewsArticleDetail | null> {
      try {
        const response = await client<ApiItemResponse<NewsArticleDetail>>('/articles/featured')
        return response.data ? normalizeArticleMedia(response.data) : null
      } catch {
        return null
      }
    },

    async getArticle(slug: string): Promise<NewsArticleDetail | null> {
      try {
        const response = await client<ApiItemResponse<NewsArticleDetail>>(`/articles/${slug}`)
        return response.data ? normalizeArticleMedia(response.data) : null
      } catch {
        return null
      }
    },

    async getRelated(slug: string): Promise<NewsArticleCard[]> {
      const response = await client<ApiCollectionResponse<NewsArticleCard>>(`/articles/${slug}/related`)
      return (response.data ?? []).map(normalizeArticleMedia)
    },

    async search(query: string, params: { category?: string; perPage?: number } = {}): Promise<NewsArticleCard[]> {
      const response = await client<ApiCollectionResponse<NewsArticleCard>>('/articles/search', {
        query: {
          q: query,
          category: params.category,
          per_page: params.perPage ?? 12,
        },
      })

      return (response.data ?? []).map(normalizeArticleMedia)
    },

    async subscribe(email: string, source: string = 'website'): Promise<{ message: string }> {
      const response = await client<{ data: { message: string; email: string } }>('/newsletter/subscribe', {
        method: 'POST',
        body: { email, source },
      })
      return { message: response.data?.message ?? 'Subscribed' }
    },
  }
}
