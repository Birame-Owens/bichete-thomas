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

export type ClientSource = 'en_ligne' | 'physique'

export type PreferenceClient = {
  id: number
  client_id: number
  coiffures_preferees: string[] | null
  options_preferees: string[] | null
  notes: string | null
  notifications_whatsapp: boolean
  notifications_promos: boolean
  created_at?: string
  updated_at?: string
}

export type ListeNoireClient = {
  id: number
  client_id: number
  raison: string | null
  actif: boolean
  blackliste_at: string | null
  retire_at: string | null
  created_at?: string
  updated_at?: string
}

export type Client = {
  id: number
  user_id: number | null
  nom: string
  prenom: string
  telephone: string
  email: string | null
  source: ClientSource
  nombre_reservations_terminees: number
  fidelite_disponible: boolean
  est_blackliste: boolean
  preferences: PreferenceClient | null
  blacklist_active?: ListeNoireClient | null
  liste_noire?: ListeNoireClient[]
  created_at?: string
  updated_at?: string
}

export type ClientForm = {
  nom: string
  prenom: string
  telephone: string
  email: string
  source: ClientSource
  nombre_reservations_terminees: string
  fidelite_disponible: boolean
}

export type ClientPreferencesForm = {
  coiffures_preferees: string
  options_preferees: string
  notes: string
  notifications_whatsapp: boolean
  notifications_promos: boolean
}
