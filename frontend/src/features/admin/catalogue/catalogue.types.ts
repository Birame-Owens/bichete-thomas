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

export type CategorieCoiffure = {
  id: number
  nom: string
  description: string | null
  image: string | null
  actif: boolean
  coiffures_count?: number
  created_at?: string
  updated_at?: string
}

export type OptionCoiffure = {
  id: number
  nom: string
  prix: number | string
  actif: boolean
  created_at?: string
  updated_at?: string
}

export type ImageCoiffure = {
  id: number
  coiffure_id: number
  url: string
  alt: string | null
  ordre: number
  principale: boolean
}

export type Coiffure = {
  id: number
  categorie_coiffure_id: number
  nom: string
  description: string | null
  image: string | null
  actif: boolean
  categorie?: CategorieCoiffure | null
  variantes?: VarianteCoiffure[]
  options?: OptionCoiffure[]
  images?: ImageCoiffure[]
  created_at?: string
  updated_at?: string
}

export type VarianteCoiffure = {
  id: number
  coiffure_id: number
  nom: string
  prix: number | string
  duree_minutes: number
  actif: boolean
  coiffure?: Coiffure | null
  created_at?: string
  updated_at?: string
}

export type CategorieForm = {
  nom: string
  description: string
  actif: boolean
  image: File | null
}

export type CoiffureForm = {
  categorie_coiffure_id: string
  nom: string
  description: string
  actif: boolean
  option_ids: number[]
  images: File[]
  variantes: Array<{
    nom: string
    prix: string
    duree_minutes: string
    actif: boolean
  }>
}

export type VarianteForm = {
  coiffure_id: string
  nom: string
  prix: string
  duree_minutes: string
  actif: boolean
}

export type OptionForm = {
  nom: string
  prix: string
  actif: boolean
}
