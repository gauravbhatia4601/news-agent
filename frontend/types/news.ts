export interface CategoryNode {
  id: number
  name: string
  slug: string
  parent_id: number | null
  description?: string | null
  children?: CategoryNode[]
}

export interface CategoryDetail {
  id: number
  name: string
  slug: string
  description?: string | null
  parent_id: number | null
  parent?: {
    id: number
    name: string
    slug: string
  } | null
  children?: Omit<CategoryNode, 'children'>[]
}

export interface ArticleCategory {
  id: number | null
  name: string
  slug: string
  parent_id: number | null
}

export interface ArticleLocation {
  id: number
  name: string
  slug: string
}

export interface NewsArticleCard {
  id: number
  slug: string
  title: string
  category: ArticleCategory | null
  location?: ArticleLocation | null
  topic_name?: string
  views: number
  author: string
  read_time_minutes: number
  image_url?: string | null
  thumbnail_url?: string | null
  published_at: string
  source_count?: number
  excerpt?: string
}

export interface NewsSource {
  name: string
  url: string
  published_at?: string | null
}

export interface FaqItem {
  question: string
  answer: string
}

export interface ArticleEntities {
  people: string[]
  organizations: string[]
  locations: string[]
  primary_topic_term: string
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
  faq_section: FaqItem[]
  internal_links: string[]
  entities: ArticleEntities
}

export interface ApiCollectionResponse<T> {
  data: T[]
}

export interface ApiPaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    total: number
    per_page: number
  }
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
}

export interface ApiItemResponse<T> {
  data: T
}

export type StoryUrgency = 'live' | 'developing' | 'ongoing' | 'concluded'

export interface NewsStory {
  id: number
  slug: string
  title: string
  description?: string | null
  category?: ArticleCategory | null
  urgency: StoryUrgency
  status: string
  started_at: string | null
  concluded_at?: string | null
  last_monitored_at?: string | null
  update_count: number
  latest_update?: { content: string; event_at: string } | null
}

export interface StoryTimelineEntry {
  id: number
  story_id: number
  content: string
  event_at: string
  source_name: string | null
  source_url: string | null
}
