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

export type ApiCollection<T> = {
  data: LaravelPaginated<T>
}

export type ApiItem<T> = {
  message?: string
  data: T
}

export type Gerante = {
  id: number
  name: string
  email: string
  role: 'gerante' | string | null
  actif: boolean
  created_at?: string
  updated_at?: string
}

export type Coiffeuse = {
  id: number
  nom: string
  prenom: string
  telephone: string | null
  pourcentage_commission: number | string
  actif: boolean
  created_at?: string
  updated_at?: string
}

export type GeranteForm = {
  name: string
  email: string
  password: string
  actif: boolean
}

export type CoiffeuseForm = {
  nom: string
  prenom: string
  telephone: string
  pourcentage_commission: string
  actif: boolean
}
