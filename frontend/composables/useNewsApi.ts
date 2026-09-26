import type {
  ApiCollectionResponse,
  ApiItemResponse,
  ApiPaginatedResponse,
  CategoryDetail,
  CategoryNode,
  NewsArticleCard,
  NewsArticleDetail,
  NewsStory,
  StoryTimelineEntry,
  StoryUrgency,
} from '~/types/news'

export interface HomeFeedCategory {
  id: number
  name: string
  slug: string
  parent_id: number | null
  children?: Omit<CategoryNode, 'children'>[]
  headlines: NewsArticleCard[]
}

export interface HomeFeed {
  featured: NewsArticleDetail | null
  hot: NewsArticleCard[]
  categories: HomeFeedCategory[]
  stories: NewsStory[]
  generated_at: string
}

function normalizeHomeFeed(feed: HomeFeed): HomeFeed {
  const card = (a: NewsArticleCard): NewsArticleCard => normalizeArticleMedia(a)
  return {
    featured: feed.featured ? normalizeArticleMedia(feed.featured) : null,
    hot: (feed.hot ?? []).map(card),
    categories: (feed.categories ?? []).map(c => ({
      ...c,
      children: c.children ?? [],
      headlines: (c.headlines ?? []).map(card),
    })),
    stories: feed.stories ?? [],
    generated_at: feed.generated_at,
  }
}

export function useNewsApi() {
  const client = $fetch.create({
    baseURL: '/api/v1',
    // SSR fetches occasionally hit transient proxy failures ('no available
    // server') — retry so a blip doesn't cache an empty/error page render
    // for the whole SWR window.
    retry: 2,
    retryDelay: 300,
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

    // Batched homepage feed — one scheduler-warmed payload replaces the old
    // featured/hot/headlines/stories/categories N+1 (16 HTTP calls → 1).
    async getHomeFeed(): Promise<HomeFeed> {
      const response = await client<{ data: HomeFeed }>('/home')
      return normalizeHomeFeed(response.data)
    },

    async getTrending(limit = 10): Promise<NewsArticleCard[]> {
      const response = await client<ApiCollectionResponse<NewsArticleCard>>('/articles/trending', {
        query: { per_page: limit },
      })

      return (response.data ?? []).map(normalizeArticleMedia)
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
        data: response.data ?? [],
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
        return response.data ?? null
      } catch {
        return null
      }
    },

    // Timeline entries are StoryUpdateResource rows (content + event_at + source),
    // NOT articles — no media normalization applies.
    async getStoryTimeline(slug: string, params: { perPage?: number; page?: number } = {}): Promise<{ data: StoryTimelineEntry[]; meta: { current_page: number; last_page: number; total: number } }> {
      const response = await client<ApiPaginatedResponse<StoryTimelineEntry>>(`/stories/${slug}/timeline`, {
        query: {
          per_page: params.perPage ?? 9,
          page: params.page ?? 1,
        },
      })

      return {
        data: response.data ?? [],
        meta: {
          current_page: response.meta?.current_page ?? 1,
          last_page: response.meta?.last_page ?? 1,
          total: response.meta?.total ?? 0,
        },
      }
    },

    async getStoryArticles(slug: string, params: { perPage?: number; page?: number } = {}): Promise<{ data: NewsArticleCard[]; meta: { current_page: number; last_page: number; total: number } }> {
      const response = await client<ApiPaginatedResponse<NewsArticleCard>>(`/stories/${slug}/articles`, {
        query: {
          per_page: params.perPage ?? 8,
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
