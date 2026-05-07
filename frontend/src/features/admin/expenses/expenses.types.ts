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

export type ExpenseCategory = {
  id: number
  nom: string
  description: string | null
  actif: boolean
  depenses_count?: number
  created_at?: string
  updated_at?: string
}

export type Expense = {
  id: number
  categorie_depense_id: number | null
  titre: string
  montant: number | string
  date_depense: string
  description: string | null
  mode_paiement: string | null
  reference: string | null
  categorie?: ExpenseCategory | null
  created_at?: string
  updated_at?: string
}

export type ExpenseSummary = {
  total_montant: number
  nombre_depenses: number
  total_mois_courant: number
  total_aujourdhui: number
}

export type ExpenseForm = {
  categorie_depense_id: string
  titre: string
  montant: string
  date_depense: string
  mode_paiement: string
  reference: string
  description: string
}

export type ExpenseCategoryForm = {
  nom: string
  description: string
  actif: boolean
}
