export type ClientApiItem<T> = {
  message?: string
  data: T
}

export type ClientCategory = {
  id: number
  nom: string
  description: string | null
  image: string | null
  coiffures_count: number
}

export type ClientCoiffureImage = {
  id: number
  url: string
  alt: string | null
  principale: boolean
}

export type ClientCoiffureVariant = {
  id: number
  nom: string
  prix: number
  duree_minutes: number
}

export type ClientCoiffureOption = {
  id: number
  nom: string
  prix: number
}

export type ClientCoiffure = {
  id: number
  nom: string
  description: string | null
  image: string | null
  categorie: {
    id: number
    nom: string
  } | null
  prix_min: number
  duree_min_minutes: number
  images: ClientCoiffureImage[]
  variantes: ClientCoiffureVariant[]
  options: ClientCoiffureOption[]
}

export type ClientPromotion = {
  id: number
  code: string
  nom: string | null
  type_reduction: 'pourcentage' | 'montant'
  valeur: number
  date_fin: string | null
}

export type ClientSettings = {
  devise: 'FCFA' | string
  telephone_whatsapp: string | null
  heure_ouverture: string
  heure_fermeture: string
  montant_acompte_defaut: number
  pourcentage_acompte: number
  limite_reservations_par_jour: number
  limite_reservations_par_creneau: number
}

export type ClientCatalogue = {
  categories: ClientCategory[]
  coiffures: ClientCoiffure[]
  promotions: ClientPromotion[]
  settings: ClientSettings
}

export type ClientAvailabilitySlot = {
  heure: string
  reservations: number
  limite: number
  disponible: boolean
  raison: 'jour_complet' | 'creneau_complet' | 'heure_passee' | null
}

export type ClientAvailability = {
  date: string
  heure_ouverture: string
  heure_fermeture: string
  limite_reservations_par_jour: number
  reservations_jour: number
  jour_complet: boolean
  limite_reservations_par_creneau: number
  creneaux: ClientAvailabilitySlot[]
}

export type ClientReservationPayload = {
  client: {
    nom: string
    prenom: string
    telephone: string
    email: string | null
  }
  coiffure_id: number
  variante_coiffure_id: number
  option_ids: number[]
  date_reservation: string
  heure_debut: string
  code_promo: string | null
  notes: string | null
}

export type ClientReservation = {
  id: number
  date_reservation: string
  heure_debut: string
  heure_fin: string
  statut: 'en_attente' | 'confirmee' | 'acompte_paye' | 'en_cours' | 'terminee' | 'annulee' | 'absence'
  montant_total: number | string
  montant_reduction: number | string
  montant_acompte: number | string
  montant_restant: number | string
  devise: string
}
