import { apiClient } from '../../../lib/apiClient'
import type {
  ApiCollection,
  ApiItem,
  Client,
  LaravelPaginated,
  Paiement,
  PaymentForm,
  PaymentLookups,
  PaymentReceipt,
  PaymentSummary,
  Reservation,
} from './payments.types'

type QueryParams = {
  page?: number
  per_page?: number
  search?: string
  statut?: string
  type?: string
  mode_paiement?: string
  reservation_id?: number | string
  client_id?: number | string
  date_from?: string
  date_to?: string
}

type CollectionResult<T, M = unknown> = {
  data: LaravelPaginated<T>
  meta?: M
}

function collection<T, M = unknown>(payload: ApiCollection<T, M>): CollectionResult<T, M> {
  return {
    data: payload.data,
    meta: payload.meta,
  }
}

function nullableNumber(value: string) {
  return value.trim() === '' ? null : Number(value)
}

function nullableText(value: string) {
  const text = value.trim()

  return text === '' ? null : text
}

function paymentPayload(form: PaymentForm) {
  return {
    reservation_id: nullableNumber(form.reservation_id),
    client_id: nullableNumber(form.client_id),
    type: form.type,
    mode_paiement: form.mode_paiement,
    montant: Number(form.montant),
    statut: form.statut,
    date_paiement: form.date_paiement || null,
    reference: nullableText(form.reference),
    notes: nullableText(form.notes),
    recu_envoye: form.recu_envoye,
    devise: 'FCFA',
  }
}

export async function getPayments(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<Paiement, PaymentSummary>>('/admin/paiements', {
    params,
  })

  return collection(response.data)
}

export async function getPayment(id: number) {
  const response = await apiClient.get<ApiItem<Paiement>>(`/admin/paiements/${id}`)

  return response.data
}

export async function createPayment(form: PaymentForm) {
  const response = await apiClient.post<ApiItem<Paiement>>('/admin/paiements', paymentPayload(form))

  return response.data
}

export async function updatePayment(id: number, form: PaymentForm) {
  const response = await apiClient.put<ApiItem<Paiement>>(`/admin/paiements/${id}`, paymentPayload(form))

  return response.data
}

export async function cancelPayment(id: number, notes?: string) {
  const response = await apiClient.patch<ApiItem<Paiement>>(`/admin/paiements/${id}/annuler`, {
    notes: nullableText(notes ?? ''),
  })

  return response.data.data
}

export async function deletePayment(id: number) {
  await apiClient.delete(`/admin/paiements/${id}`)
}

export async function getReceipt(id: number) {
  const response = await apiClient.get<{ data: PaymentReceipt }>(`/admin/paiements/${id}/recu`)

  return response.data.data
}

export async function markReceiptSent(id: number) {
  const response = await apiClient.patch<ApiItem<Paiement>>(`/admin/paiements/${id}/recu-envoye`, {})

  return response.data.data
}

export async function getPaymentLookups(): Promise<PaymentLookups> {
  const [reservations, clients] = await Promise.all([
    apiClient.get<ApiCollection<Reservation>>('/admin/reservations', {
      params: { per_page: 100 },
    }),
    apiClient.get<ApiCollection<Client>>('/admin/clients', {
      params: { per_page: 100, blackliste: false },
    }),
  ])

  return {
    reservations: reservations.data.data.data,
    clients: clients.data.data.data,
  }
}
