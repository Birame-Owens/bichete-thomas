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
  receipt?: PaymentReceipt
}

export type PaymentType = 'acompte' | 'solde' | 'complet' | 'remboursement' | 'ajustement'
export type PaymentMethod = 'especes' | 'wave' | 'orange_money' | 'carte_bancaire' | 'virement' | 'autre'
export type PaymentStatus = 'en_attente' | 'valide' | 'annule' | 'rembourse'

export type Client = {
  id: number
  nom: string
  prenom: string
  telephone: string
  email?: string | null
}

export type ReservationDetail = {
  id: number
  coiffure_nom: string
  variante_nom: string | null
  quantite: number
  montant_total: number | string
}

export type Reservation = {
  id: number
  client_id: number | null
  date_reservation: string
  heure_debut: string
  statut: string
  montant_total: number | string
  montant_acompte: number | string
  montant_restant: number | string
  devise: 'FCFA'
  client?: Client | null
  details?: ReservationDetail[]
}

export type Caisse = {
  id: number
  date: string
  statut: 'ouverte' | 'fermee'
}

export type Paiement = {
  id: number
  reservation_id: number | null
  client_id: number | null
  caisse_id: number | null
  mouvement_caisse_id: number | null
  numero_recu: string
  type: PaymentType
  mode_paiement: PaymentMethod
  montant: number | string
  devise: 'FCFA'
  statut: PaymentStatus
  date_paiement: string
  reference: string | null
  notes: string | null
  recu_envoye: boolean
  recu_envoye_at: string | null
  client?: Client | null
  reservation?: Reservation | null
  caisse?: Caisse | null
  created_at?: string
  updated_at?: string
}

export type PaymentSummary = {
  total_paye: number
  total_acomptes: number
  total_soldes: number
  total_attente: number
  total_annule: number
  nombre_paiements: number
}

export type PaymentForm = {
  reservation_id: string
  client_id: string
  nouveau_client: boolean
  client_nom: string
  client_prenom: string
  client_telephone: string
  client_email: string
  type: PaymentType
  mode_paiement: PaymentMethod
  montant: string
  statut: PaymentStatus
  date_paiement: string
  reference: string
  notes: string
  recu_envoye: boolean
}

export type PaymentLookups = {
  reservations: Reservation[]
  clients: Client[]
}

export type PaymentReceipt = {
  salon: {
    nom: string
    description: string
    telephone_whatsapp: string | null
    devise: string
  }
  numero_recu: string
  date: string
  client: {
    id: number | null
    nom: string
    telephone: string | null
    email: string | null
  }
  reservation: {
    id: number
    date_reservation: string
    heure_debut: string
    statut: string
    services: Array<{
      coiffure: string
      variante: string | null
      quantite: number
      montant: number
    }>
  } | null
  paiement: {
    id: number
    type: PaymentType
    mode_paiement: PaymentMethod
    montant: number
    devise: string
    statut: PaymentStatus
    reference: string | null
    notes: string | null
    recu_envoye: boolean
    recu_envoye_at: string | null
  }
  totaux: {
    montant_reservation: number
    montant_deja_paye: number
    reste_a_payer: number
  }
}
