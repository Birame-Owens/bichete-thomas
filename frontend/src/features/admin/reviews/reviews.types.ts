export type LaravelPaginated<T> = {
  current_page: number
  data: T[]
  first_page_url: string | null
  from: number | null
  last_page: number
  last_page_url: string | null
  links: Array<{
    url: string | null
    label: string
    active: boolean
  }>
  next_page_url: string | null
  path: string
  per_page: number
  prev_page_url: string | null
  to: number | null
  total: number
}

export type ApiCollection<T, M = unknown> = {
  data: LaravelPaginated<T>
  meta?: M
}

export type ApiItem<T> = {
  message?: string
  data: T
}

export type ReviewStatus = 'en_attente' | 'approuve' | 'rejete'

export type ReviewCoiffure = {
  id: number
  nom: string
  image: string | null
}

export type ReviewClient = {
  id: number
  nom: string
  prenom: string
  telephone: string | null
  email: string | null
}

export type ReviewReservation = {
  id: number
  date_reservation: string
  statut: string
}

export type CoiffureReview = {
  id: number
  coiffure_id: number
  client_id: number | null
  reservation_id: number | null
  nom_client: string
  telephone: string | null
  email: string | null
  note: number
  commentaire: string
  photo_url: string | null
  statut: ReviewStatus
  verifie: boolean
  publie_at: string | null
  created_at: string
  coiffure?: ReviewCoiffure | null
  client?: ReviewClient | null
  reservation?: ReviewReservation | null
}

export type ReviewSummary = {
  total: number
  en_attente: number
  approuves: number
  rejetes: number
}
