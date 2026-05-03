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

export type DiscountType = 'pourcentage' | 'montant'

export type CodePromo = {
  id: number
  code: string
  nom: string | null
  type_reduction: DiscountType
  valeur: number | string
  date_debut: string | null
  date_fin: string | null
  limite_utilisation: number | null
  nombre_utilisations: number
  actif: boolean
  created_at?: string
  updated_at?: string
}

export type RegleFidelite = {
  id: number
  nom: string
  nombre_reservations_requis: number
  type_recompense: DiscountType
  valeur_recompense: number | string
  actif: boolean
  created_at?: string
  updated_at?: string
}

export type CodePromoForm = {
  code: string
  nom: string
  type_reduction: DiscountType
  valeur: string
  date_debut: string
  date_fin: string
  limite_utilisation: string
  actif: boolean
}

export type RegleFideliteForm = {
  nom: string
  nombre_reservations_requis: string
  type_recompense: DiscountType
  valeur_recompense: string
  actif: boolean
}
