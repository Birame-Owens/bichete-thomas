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

export type ClientCoiffureReviewSummary = {
  moyenne: number
  total: number
}

export type ClientCoiffureReview = {
  id: number
  nom_client: string
  note: number
  commentaire: string
  photo_url: string | null
  verifie: boolean
  statut: 'en_attente' | 'approuve' | 'rejete'
  publie_at: string | null
}

export type ClientCoiffureRecentPrestation = {
  reservation_id: number
  date_reservation: string
  statut: string
  cliente: string
  variante_nom: string | null
  montant_total: number
}

export type ClientRelatedCoiffure = {
  id: number
  nom: string
  image: string | null
  prix_min: number
  duree_min_minutes: number
}

export type ClientCoiffure = {
  id: number
  nom: string
  description: string | null
  image: string | null
  est_populaire: boolean
  est_nouveaute: boolean
  categorie: {
    id: number
    nom: string
  } | null
  prix_min: number
  duree_min_minutes: number
  images: ClientCoiffureImage[]
  avis_resume: ClientCoiffureReviewSummary
  avis: ClientCoiffureReview[]
  prestations_recentes: ClientCoiffureRecentPrestation[]
  coiffures_liees: ClientRelatedCoiffure[]
  variantes: ClientCoiffureVariant[]
  options: ClientCoiffureOption[]
}

export type ClientCoiffureReviewPayload = {
  nom_client: string
  telephone: string | null
  email: string | null
  note: number
  commentaire: string
}

export type ClientCoiffureReviewResponse = {
  message?: string
  data: ClientCoiffureReview
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
  image_accueil: string | null
  telephone_whatsapp: string | null
  heure_ouverture: string
  heure_fermeture: string
  jours_fermeture: string[]
  montant_acompte_defaut: number
  pourcentage_acompte: number
  limite_reservations_par_jour: number
  limite_reservations_par_creneau: number
  paiements_en_ligne: {
    wave: boolean
    orange_money: boolean
    carte_bancaire: boolean
  }
}

export type ClientGalleryPhoto = {
  id: number
  url: string
  titre: string | null
  sous_titre: string | null
}

export type ClientCatalogue = {
  categories: ClientCategory[]
  coiffures: ClientCoiffure[]
  promotions: ClientPromotion[]
  gallery: ClientGalleryPhoto[]
  settings: ClientSettings
}

export type ClientAvailabilitySlot = {
  heure: string
  reservations: number
  limite: number
  disponible: boolean
  raison: 'jour_ferme' | 'jour_complet' | 'creneau_complet' | 'heure_passee' | null
}

export type ClientAvailability = {
  date: string
  heure_ouverture: string
  heure_fermeture: string
  limite_reservations_par_jour: number
  reservations_jour: number
  jour_complet: boolean
  jour_ferme: boolean
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
  mode_paiement: ClientPaymentMethod
  idempotency_key: string | null
  reference_paiement: string | null
  success_url: string | null
  cancel_url: string | null
}

export type ClientPaymentMethod = 'wave' | 'orange_money' | 'carte_bancaire'

export type ClientPayment = {
  id: number
  reservation_id: number | null
  client_id: number | null
  numero_recu: string
  type: 'acompte' | 'solde' | 'complet' | 'remboursement' | 'ajustement'
  mode_paiement: ClientPaymentMethod
  montant: number | string
  devise: string
  statut: 'en_attente' | 'valide' | 'annule' | 'rembourse'
  reference: string | null
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

export type ClientReservationResponse = {
  message?: string
  data: ClientReservation
  payment: ClientPayment
  checkout_url: string | null
  requires_redirect: boolean
}

export type ClientPaymentClientInfo = {
  prenom: string
  nom: string
  telephone: string
  email?: string | null
}

export type ClientPaymentWithRelations = ClientPayment & {
  client?: ClientPaymentClientInfo | null
  reservation?: (ClientReservation & { client?: ClientPaymentClientInfo | null }) | null
}

export type ClientStripeConfirmation = {
  message?: string
  data: ClientPaymentWithRelations
}

// Phase 5 etape 1 : lookup public d un client par telephone E.164.
// Privacy-first cote backend : la reponse ne contient JAMAIS email/id/telephone.
export type ClientLookupResponse = {
  found: boolean
  nom: string | null
  prenom: string | null
}

// Phase 5 etape 2 : session client persistante via cookie httpOnly.
export type ClientSession = {
  nom: string
  prenom: string
  telephone: string
}

export type ClientMagicLinkVerifyResponse = {
  message: string
  data: ClientSession
}

export type ClientAuthRequestResponse = {
  message: string
  debug_magic_url?: string
}

export type ClientRegisterPayload = {
  prenom: string
  nom: string
  telephone: string
  email: string | null
}

// Phase 5 etape 3 : avis verifies post-prestation via lien WhatsApp.
export type AvisPrefill = {
  prenom: string
  coiffure_nom: string
}

export type AvisVerifiePayload = {
  note: number
  commentaire: string
}
