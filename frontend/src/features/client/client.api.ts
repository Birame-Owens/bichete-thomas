import { apiAssetUrl } from '../../lib/apiAssets'
import { apiClient } from '../../lib/apiClient'
import type {
  ClientApiItem,
  ClientAvailability,
  ClientCatalogue,
  ClientCategory,
  ClientCoiffure,
  ClientCoiffureReviewPayload,
  ClientCoiffureReviewResponse,
  ClientReservationPayload,
  ClientReservationResponse,
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
  }
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
