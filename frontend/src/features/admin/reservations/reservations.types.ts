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

export type ReservationStatus =
  | 'en_attente'
  | 'confirmee'
  | 'acompte_paye'
  | 'en_cours'
  | 'terminee'
  | 'annulee'
  | 'absence'

export type ReservationSource = 'admin' | 'en_ligne' | 'whatsapp' | 'telephone' | 'physique'
export type DiscountType = 'pourcentage' | 'montant'

export type Client = {
  id: number
  nom: string
  prenom: string
  telephone: string
  email: string | null
  nombre_reservations_terminees: number
  fidelite_disponible: boolean
  est_blackliste: boolean
}

export type Coiffeuse = {
  id: number
  nom: string
  prenom: string
  telephone: string | null
  actif: boolean
}

export type OptionCoiffure = {
  id: number
  nom: string
  prix: number | string
  actif: boolean
}

export type VarianteCoiffure = {
  id: number
  coiffure_id: number
  nom: string
  prix: number | string
  duree_minutes: number
  actif: boolean
}

export type Coiffure = {
  id: number
  nom: string
  actif: boolean
  variantes?: VarianteCoiffure[]
  options?: OptionCoiffure[]
}

export type CodePromo = {
  id: number
  code: string
  nom: string | null
  type_reduction: DiscountType
  valeur: number | string
  actif: boolean
}

export type RegleFidelite = {
  id: number
  nom: string
  nombre_reservations_requis: number
  type_recompense: DiscountType
  valeur_recompense: number | string
  actif: boolean
}

export type DetailReservation = {
  id: number
  reservation_id: number
  coiffure_id: number | null
  variante_coiffure_id: number | null
  coiffure_nom: string
  variante_nom: string | null
  prix_unitaire: number | string
  duree_minutes: number
  quantite: number
  option_ids: number[] | null
  options_snapshot: Array<{
    id: number
    nom: string
    prix: number
  }> | null
  montant_options: number | string
  montant_total: number | string
  ordre: number
  coiffure?: Coiffure | null
  variante?: VarianteCoiffure | null
}

export type Reservation = {
  id: number
  client_id: number | null
  coiffeuse_id: number | null
  code_promo_id: number | null
  regle_fidelite_id: number | null
  date_reservation: string
  heure_debut: string
  heure_fin: string
  duree_totale_minutes: number
  statut: ReservationStatus
  source: ReservationSource
  montant_total: number | string
  montant_reduction: number | string
  montant_acompte: number | string
  montant_restant: number | string
  devise: 'FCFA'
  fidelite_appliquee: boolean
  notes: string | null
  annulee_at: string | null
  terminee_at: string | null
  client?: Client | null
  coiffeuse?: Coiffeuse | null
  code_promo?: CodePromo | null
  regle_fidelite?: RegleFidelite | null
  details?: DetailReservation[]
  created_at?: string
  updated_at?: string
}

export type ReservationDetailForm = {
  coiffure_id: string
  variante_coiffure_id: string
  quantite: string
  option_ids: number[]
}

export type ReservationForm = {
  client_id: string
  nouveau_client: boolean
  client_nom: string
  client_prenom: string
  client_telephone: string
  client_email: string
  coiffeuse_id: string
  date_reservation: string
  heure_debut: string
  statut: ReservationStatus
  source: ReservationSource
  code_promo_id: string
  regle_fidelite_id: string
  montant_acompte: string
  notes: string
  details: ReservationDetailForm[]
}

export type ReservationLookups = {
  clients: Client[]
  coiffeuses: Coiffeuse[]
  coiffures: Coiffure[]
  codesPromo: CodePromo[]
  reglesFidelite: RegleFidelite[]
}
