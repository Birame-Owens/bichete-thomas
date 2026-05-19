export type LogUser = {
  id: number
  name: string
  email: string
  role_id: number | null
}

export type LogSysteme = {
  id: number
  user_id: number | null
  action: string
  module: string | null
  description: string | null
  subject_type: string | null
  subject_id: number | null
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
  metadata: Record<string, unknown> | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
  user: LogUser | null
}

export type LaravelPaginated<T> = {
  current_page: number
  data: T[]
  last_page: number
  per_page: number
  total: number
}

export type LogQueryParams = {
  page?: number
  per_page?: number
  action?: string
  module?: string
  user_id?: number
  date_debut?: string
  date_fin?: string
  search?: string
}
