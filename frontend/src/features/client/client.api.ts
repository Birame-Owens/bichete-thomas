import { apiAssetUrl } from '../../lib/apiAssets'
import { apiClient } from '../../lib/apiClient'
import type {
  AvisPrefill,
  AvisVerifiePayload,
  ClientApiItem,
  ClientAuthRequestResponse,
  ClientAvailability,
  ClientBoutique,
  ClientCatalogue,
  ClientCategory,
  ClientProduit,
  ClientProduitDetail,
  ClientCoiffure,
  ClientCoiffureReviewPayload,
  ClientCoiffureReviewResponse,
  ClientLookupResponse,
  ClientMagicLinkVerifyResponse,
  ClientRegisterPayload,
  ClientReservationPayload,
  ClientReservationResponse,
  ClientSession,
  ClientStripeConfirmation,
} from './client.types'

type CatalogueParams = {
  categorie_id?: number | null
  search?: string
}

function normalizeCategory(category: ClientCategory): ClientCategory {
  return {
    ...category,
    image: apiAssetUrl(category.image),
  }
}

function normalizeCoiffure(coiffure: ClientCoiffure): ClientCoiffure {
  return {
    ...coiffure,
    image: apiAssetUrl(coiffure.image),
    avis_resume: coiffure.avis_resume ?? { moyenne: 0, total: 0 },
    images: (coiffure.images ?? []).map((image) => ({
      ...image,
      url: apiAssetUrl(image.url) ?? image.url,
    })),
    avis: (coiffure.avis ?? []).map((avis) => ({
      ...avis,
      photo_url: apiAssetUrl(avis.photo_url),
    })),
    prestations_recentes: coiffure.prestations_recentes ?? [],
    coiffures_liees: (coiffure.coiffures_liees ?? []).map((related) => ({
      ...related,
      image: apiAssetUrl(related.image),
    })),
  }
}

function normalizeCatalogue(catalogue: ClientCatalogue): ClientCatalogue {
  return {
    ...catalogue,
    categories: catalogue.categories.map(normalizeCategory),
    coiffures: catalogue.coiffures.map(normalizeCoiffure),
    gallery: (catalogue.gallery ?? []).map((photo) => ({
      ...photo,
      url: apiAssetUrl(photo.url) ?? photo.url,
    })),
    settings: {
      ...catalogue.settings,
      image_accueil: apiAssetUrl(catalogue.settings.image_accueil),
    },
  }
}

// -----------------------------------------------------------------
// Phase 2 ecommerce : boutique publique
// -----------------------------------------------------------------

function normalizeClientProduit<T extends ClientProduit>(produit: T): T {
  return {
    ...produit,
    image: apiAssetUrl(produit.image),
  }
}

export async function getClientBoutique() {
  const response = await apiClient.get<ClientApiItem<ClientBoutique>>('/client/boutique')
  const data = response.data.data

  return {
    ...data,
    categories: data.categories.map((c) => ({ ...c, image: apiAssetUrl(c.image) })),
    produits: data.produits.map(normalizeClientProduit),
  }
}

export async function getClientProduitDetail(slug: string) {
  const response = await apiClient.get<ClientApiItem<ClientProduitDetail>>(`/client/boutique/${slug}`)
  const produit = normalizeClientProduit(response.data.data)

  return {
    ...produit,
    images: (produit.images ?? []).map((image) => ({
      ...image,
      url: apiAssetUrl(image.url) ?? image.url,
      url_miniature: apiAssetUrl(image.url_miniature),
    })),
  }
}

export type BoutiqueCommandePayload = {
  client: { prenom: string; nom: string; telephone: string; email: string | null }
  mode_livraison: 'domicile' | 'boutique'
  adresse_livraison: string | null
  instructions_livraison: string | null
  mode_paiement: 'wave' | 'orange_money' | 'livraison'
  articles: Array<{ produit_id: number; quantite: number; couleur: string | null; taille: string | null }>
  success_url: string
  cancel_url: string
}

export type BoutiqueCommandeResponse = {
  numero_commande: string
  montant_total: number
  checkout_url: string | null
  requires_redirect: boolean
}

export async function createBoutiqueCommande(payload: BoutiqueCommandePayload) {
  const response = await apiClient.post<ClientApiItem<BoutiqueCommandeResponse>>('/client/boutique/commandes', payload)

  return response.data.data
}

export async function getClientCatalogue(params?: CatalogueParams) {
  const response = await apiClient.get<ClientApiItem<ClientCatalogue>>('/client/catalogue', {
    params,
  })

  return normalizeCatalogue(response.data.data)
}

export async function getClientCoiffureDetails(id: number) {
  const response = await apiClient.get<ClientApiItem<ClientCoiffure>>(`/client/catalogue/${id}`)

  return normalizeCoiffure(response.data.data)
}

export async function createClientCoiffureReview(id: number, payload: ClientCoiffureReviewPayload) {
  const response = await apiClient.post<ClientCoiffureReviewResponse>(`/client/catalogue/${id}/avis`, payload)

  return response.data
}

export async function getClientAvailability(date: string, intervalMinutes = 60) {
  const response = await apiClient.get<ClientApiItem<ClientAvailability>>('/client/reservations/disponibilites', {
    params: {
      date,
      interval_minutes: intervalMinutes,
    },
  })

  return response.data.data
}

export async function createClientReservation(payload: ClientReservationPayload) {
  const response = await apiClient.post<ClientReservationResponse>('/client/reservations', payload)

  return response.data
}

export async function confirmStripeCheckout(sessionId: string) {
  const response = await apiClient.post<ClientStripeConfirmation>('/client/paiements/stripe/confirmer', {
    session_id: sessionId,
  })

  return response.data
}

export async function confirmPaytechReturn(paymentId: string, signature: string) {
  const response = await apiClient.post<ClientStripeConfirmation>('/client/paiements/paytech/confirmer', {
    paiement_id: paymentId,
    signature,
  })

  return response.data
}

export async function confirmNaboopayReturn(paymentId: string, signature: string) {
  const response = await apiClient.post<ClientStripeConfirmation>('/client/paiements/naboopay/confirmer', {
    paiement_id: paymentId,
    signature,
  })

  return response.data
}

// -----------------------------------------------------------------
// Phase 5 etape 2 : session client via magic link WhatsApp
// -----------------------------------------------------------------

export async function verifyMagicLink(token: string) {
  const response = await apiClient.post<ClientMagicLinkVerifyResponse>('/client/auth/magic-link', { token })
  return response.data
}

export async function requestClientLogin(telephone: string) {
  const response = await apiClient.post<ClientAuthRequestResponse>('/client/auth/login', { telephone })
  return response.data
}

export async function registerClient(payload: ClientRegisterPayload) {
  const response = await apiClient.post<ClientAuthRequestResponse>('/client/auth/register', payload)
  return response.data
}

// 401 si pas de session valide — le caller doit catcher silencieusement.
export async function getClientSession() {
  const response = await apiClient.get<ClientApiItem<ClientSession>>('/client/session')
  return response.data.data
}

export async function logoutClientSession() {
  await apiClient.delete('/client/session')
}

// -----------------------------------------------------------------
// Phase 5 etape 3 : avis verifies post-prestation
// -----------------------------------------------------------------

export async function getAvisPrefill(token: string) {
  const response = await apiClient.get<ClientApiItem<AvisPrefill>>(`/client/avis/${token}`)
  return response.data.data
}

export async function submitVerifiedAvis(token: string, payload: AvisVerifiePayload) {
  const response = await apiClient.post<{ message: string }>(`/client/avis/${token}`, payload)
  return response.data
}

/**
 * Lookup public d un client par tel E.164 (Phase 5 etape 1).
 *
 * Doit recevoir un tel deja au format E.164 (`+221771234567`). Le backend
 * normalise quand meme via libphonenumber, donc un input raw passe aussi,
 * mais la convention frontend est d envoyer du E.164 (produit par
 * <PhoneInput>).
 *
 * Backend renvoie 200 + `{found:false}` sur tel inconnu OU invalide, jamais
 * 422 : c est volontaire pour ne pas leak la validite du format. Notre
 * appelant peut donc traiter "non parsable" et "inconnu" pareillement
 * (= ne rien prefiller).
 *
 * Le retry interceptor d apiClient (I12) couvre seulement les GET ; le 429
 * du throttle:5,1 backend n est pas retry, donc le caller decide quoi faire
 * (en pratique : silence, on attend la frappe suivante).
 */
export async function lookupClientByPhone(tel: string, signal?: AbortSignal) {
  const response = await apiClient.get<ClientLookupResponse>('/client/lookup', {
    params: { tel },
    signal,
  })

  return response.data
}
