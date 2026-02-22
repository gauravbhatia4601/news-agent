export interface NewsArticleCard {
  id: number
  slug: string
  title: string
  category: string
  topic_name?: string
  views: number
  author: string
  read_time_minutes: number
  image_url?: string | null
  thumbnail_url?: string | null
  published_at: string
  source_count?: number
}

export interface NewsSource {
  name: string
  url: string
  published_at?: string | null
}

export interface NewsArticleDetail extends NewsArticleCard {
  content: string
  meta_title?: string | null
  meta_description?: string | null
  meta_keywords?: string | null
  sources: NewsSource[]
  citations: Array<{
    id: number
    source_name: string
    source_url: string
  }>
}

export interface ApiCollectionResponse<T> {
  data: T[]
}

export interface ApiItemResponse<T> {
  data: T
}
