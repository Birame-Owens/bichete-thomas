import { apiClient } from '../../lib/apiClient'
import type { LaravelPaginated, Reservation, ReservationStatus } from '../admin/reservations/reservations.types'

export type GeranteReservationDetail = {
  coiffure_id: number
  variante_coiffure_id: number
  quantite?: number
}

export type GeranteReservationForm = {
  client_id: number
  coiffeuse_id?: number | null
  date_reservation: string
  heure_debut: string
  details: GeranteReservationDetail[]
  notes?: string
  type_paiement: 'acompte' | 'soldee'
  mode_paiement: string
}

type QueryParams = {
  page?: number
  per_page?: number
  search?: string
  statut?: string
  date?: string
  date_from?: string
  date_to?: string
}

export async function getGeranteReservations(params: QueryParams = {}): Promise<LaravelPaginated<Reservation>> {
  const response = await apiClient.get<{ data: LaravelPaginated<Reservation> }>('/gerante/reservations', { params })
  return response.data.data
}

export async function getGeranteReservation(id: number): Promise<Reservation> {
  const response = await apiClient.get<{ data: Reservation }>(`/gerante/reservations/${id}`)
  return response.data.data
}

export type SoldeInfo = {
  enregistrer_paiement: boolean
  mode_paiement_solde?: string
}

export async function createGeranteReservation(form: GeranteReservationForm): Promise<Reservation> {
  const response = await apiClient.post<{ data: Reservation }>('/gerante/reservations', form)
  return response.data.data
}

export async function updateGeranteReservationStatus(
  id: number,
  statut: ReservationStatus,
  raison?: string,
  solde?: SoldeInfo,
): Promise<Reservation> {
  const response = await apiClient.patch<{ data: Reservation }>(`/gerante/reservations/${id}/statut`, {
    statut,
    ...(raison ? { raison } : {}),
    ...(solde ?? {}),
  })
  return response.data.data
}
