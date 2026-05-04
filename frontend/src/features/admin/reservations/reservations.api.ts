import { apiClient } from '../../../lib/apiClient'
import type {
  ApiCollection,
  ApiItem,
  Client,
  CodePromo,
  Coiffeuse,
  Coiffure,
  LaravelPaginated,
  RegleFidelite,
  Reservation,
  ReservationForm,
  ReservationLookups,
  ReservationStatus,
} from './reservations.types'

type QueryParams = {
  page?: number
  per_page?: number
  search?: string
  statut?: string
  client_id?: number | string
  coiffeuse_id?: number | string
  date?: string
  date_from?: string
  date_to?: string
}

function collection<T>(payload: ApiCollection<T>): LaravelPaginated<T> {
  return payload.data
}

function nullableNumber(value: string) {
  return value.trim() === '' ? null : Number(value)
}

function nullableText(value: string) {
  const text = value.trim()

  return text === '' ? null : text
}

function reservationPayload(form: ReservationForm) {
  return {
    client_id: Number(form.client_id),
    coiffeuse_id: nullableNumber(form.coiffeuse_id),
    date_reservation: form.date_reservation,
    heure_debut: form.heure_debut,
    statut: form.statut,
    source: form.source,
    code_promo_id: nullableNumber(form.code_promo_id),
    regle_fidelite_id: nullableNumber(form.regle_fidelite_id),
    montant_acompte: nullableNumber(form.montant_acompte),
    devise: 'FCFA',
    notes: nullableText(form.notes),
    details: form.details.map((detail) => ({
      coiffure_id: Number(detail.coiffure_id),
      variante_coiffure_id: Number(detail.variante_coiffure_id),
      quantite: Number(detail.quantite || 1),
      option_ids: detail.option_ids,
    })),
  }
}

export async function getReservations(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<Reservation>>('/admin/reservations', {
    params,
  })

  return collection(response.data)
}

export async function getReservation(id: number) {
  const response = await apiClient.get<ApiItem<Reservation>>(`/admin/reservations/${id}`)

  return response.data.data
}

export async function createReservation(form: ReservationForm) {
  const response = await apiClient.post<ApiItem<Reservation>>('/admin/reservations', reservationPayload(form))

  return response.data.data
}

export async function updateReservation(id: number, form: ReservationForm) {
  const response = await apiClient.put<ApiItem<Reservation>>(`/admin/reservations/${id}`, reservationPayload(form))

  return response.data.data
}

export async function updateReservationStatus(id: number, statut: ReservationStatus, notes?: string | null) {
  const response = await apiClient.patch<ApiItem<Reservation>>(`/admin/reservations/${id}/statut`, {
    statut,
    notes,
  })

  return response.data.data
}

export async function deleteReservation(id: number) {
  await apiClient.delete(`/admin/reservations/${id}`)
}

export async function getReservationLookups(): Promise<ReservationLookups> {
  const [clients, coiffeuses, coiffures, codesPromo, reglesFidelite] = await Promise.all([
    apiClient.get<ApiCollection<Client>>('/admin/clients', {
      params: { per_page: 100, blackliste: false },
    }),
    apiClient.get<ApiCollection<Coiffeuse>>('/admin/coiffeuses', {
      params: { per_page: 100, actif: true },
    }),
    apiClient.get<ApiCollection<Coiffure>>('/admin/coiffures', {
      params: { per_page: 100, actif: true },
    }),
    apiClient.get<ApiCollection<CodePromo>>('/admin/codes-promo', {
      params: { per_page: 100, actif: true },
    }),
    apiClient.get<ApiCollection<RegleFidelite>>('/admin/regles-fidelite', {
      params: { per_page: 100, actif: true },
    }),
  ])

  return {
    clients: clients.data.data.data,
    coiffeuses: coiffeuses.data.data.data,
    coiffures: coiffures.data.data.data,
    codesPromo: codesPromo.data.data.data,
    reglesFidelite: reglesFidelite.data.data.data,
  }
}
