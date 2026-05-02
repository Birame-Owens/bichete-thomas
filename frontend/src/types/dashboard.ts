export type ClientRecent = {
  id: number
  nom: string
  prenom: string
  telephone: string
  source: 'en_ligne' | 'physique'
  created_at: string
}

export type CoiffeuseActive = {
  id: number
  nom: string
  prenom: string
  telephone: string | null
  pourcentage_commission: string
}

export type DashboardStats = {
  total_clients: number
  total_coiffeuses_actives: number
  total_coiffures_actives: number
  clients_recents: ClientRecent[]
  coiffeuses_actives: CoiffeuseActive[]

  // Pending future modules
  chiffre_affaires: null
  reservations_aujourd_hui: null
  paiements_recents: null
  coiffures_populaires: null
  coiffeuses_productives: null
  depenses_recentes: null
}
