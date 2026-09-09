import type {
  ApiCollectionResponse,
  ApiItemResponse,
  ApiPaginatedResponse,
  CategoryDetail,
  CategoryNode,
  NewsArticleCard,
  NewsArticleDetail,
  NewsStory,
  StoryUrgency,
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

    async getLatestPaginated(params: { category?: string; perPage?: number; page?: number } = {}): Promise<{ data: NewsArticleCard[]; meta: { current_page: number; last_page: number; total: number } }> {
      const response = await client<ApiPaginatedResponse<NewsArticleCard>>('/articles', {
        query: {
          category: params.category,
          per_page: params.perPage ?? 12,
          page: params.page ?? 1,
        },
      })

      return {
        data: (response.data ?? []).map(normalizeArticleMedia),
        meta: {
          current_page: response.meta?.current_page ?? 1,
          last_page: response.meta?.last_page ?? 1,
          total: response.meta?.total ?? 0,
        },
      }
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

    async getHeadlines(limit = 5, category?: string): Promise<NewsArticleCard[]> {
      const response = await client<ApiCollectionResponse<NewsArticleCard>>('/articles/headlines', {
        query: { per_page: limit, category },
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

    async getMarketData(): Promise<any[]> {
      const response = await client<{ data: any[] }>('/market')
      return response.data ?? []
    },

    async getStories(params: { urgency?: StoryUrgency; perPage?: number; page?: number } = {}): Promise<{ data: NewsStory[]; meta: { current_page: number; last_page: number; total: number } }> {
      const response = await client<ApiPaginatedResponse<NewsStory>>('/stories', {
        query: {
          urgency: params.urgency,
          per_page: params.perPage ?? 12,
          page: params.page ?? 1,
        },
      })

      return {
        data: (response.data ?? []).map((s) => ({
          ...s,
          latest_update: s.latest_update ? normalizeArticleMedia(s.latest_update) : null,
        })),
        meta: {
          current_page: response.meta?.current_page ?? 1,
          last_page: response.meta?.last_page ?? 1,
          total: response.meta?.total ?? 0,
        },
      }
    },

    async getStory(slug: string): Promise<NewsStory | null> {
      try {
        const response = await client<ApiItemResponse<NewsStory>>(`/stories/${slug}`)
        const story = response.data
        if (!story) return null
        return {
          ...story,
          latest_update: story.latest_update ? normalizeArticleMedia(story.latest_update) : null,
        }
      } catch {
        return null
      }
    },

    async getStoryTimeline(slug: string, params: { perPage?: number; page?: number } = {}): Promise<{ data: NewsArticleCard[]; meta: { current_page: number; last_page: number; total: number } }> {
      const response = await client<ApiPaginatedResponse<NewsArticleCard>>(`/stories/${slug}/timeline`, {
        query: {
          per_page: params.perPage ?? 9,
          page: params.page ?? 1,
        },
      })

      return {
        data: (response.data ?? []).map(normalizeArticleMedia),
        meta: {
          current_page: response.meta?.current_page ?? 1,
          last_page: response.meta?.last_page ?? 1,
          total: response.meta?.total ?? 0,
        },
      }
    },
  }
}
