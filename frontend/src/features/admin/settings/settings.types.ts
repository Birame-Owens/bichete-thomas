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

export type SettingType = 'string' | 'integer' | 'decimal' | 'boolean' | 'time' | 'json'

export type SystemSetting = {
  id: number
  cle: string
  valeur: {
    value: string | number | boolean | null
  } | null
  type: SettingType
  description: string | null
  modifiable: boolean
  created_at?: string
  updated_at?: string
}

export type ReservationSettingsForm = {
  montant_acompte_defaut: string
  pourcentage_acompte: string
  heure_ouverture: string
  heure_fermeture: string
  telephone_whatsapp: string
  devise: 'FCFA'
  delai_annulation_heures: string
  seuil_retard_minutes: string
  seuil_absence_minutes: string
}
