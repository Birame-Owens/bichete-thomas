import { apiClient } from '../../../lib/apiClient'
import type {
  ApiCollection,
  ApiItem,
  Client,
  ClientForm,
  ClientPreferencesForm,
  LaravelPaginated,
  PreferenceClient,
} from './clients.types'

type QueryParams = {
  page?: number
  search?: string
  source?: string
  blackliste?: boolean | string
  fidelite_disponible?: boolean | string
  per_page?: number
}

function collection<T>(payload: ApiCollection<T>): LaravelPaginated<T> {
  return payload.data
}

function cleanText(value: string) {
  const text = value.trim()

  return text === '' ? null : text
}

function tagsFromText(value: string) {
  const tags = value
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)

  return tags.length === 0 ? null : tags
}

function textFromTags(value?: string[] | null) {
  return value?.join(', ') ?? ''
}

function clientPayload(form: ClientForm) {
  return {
    nom: form.nom.trim(),
    prenom: form.prenom.trim(),
    telephone: form.telephone.trim(),
    email: cleanText(form.email),
    source: form.source,
    nombre_reservations_terminees: Number(form.nombre_reservations_terminees || 0),
    fidelite_disponible: form.fidelite_disponible,
  }
}

function preferencesPayload(form: ClientPreferencesForm) {
  return {
    coiffures_preferees: tagsFromText(form.coiffures_preferees),
    options_preferees: tagsFromText(form.options_preferees),
    notes: cleanText(form.notes),
    notifications_whatsapp: form.notifications_whatsapp,
    notifications_promos: form.notifications_promos,
  }
}

export function preferencesToForm(client: Client): ClientPreferencesForm {
  return {
    coiffures_preferees: textFromTags(client.preferences?.coiffures_preferees),
    options_preferees: textFromTags(client.preferences?.options_preferees),
    notes: client.preferences?.notes ?? '',
    notifications_whatsapp: client.preferences?.notifications_whatsapp ?? true,
    notifications_promos: client.preferences?.notifications_promos ?? true,
  }
}

export async function getClients(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<Client>>('/admin/clients', {
    params,
  })

  return collection(response.data)
}

export async function getClient(id: number) {
  const response = await apiClient.get<ApiItem<Client>>(`/admin/clients/${id}`)

  return response.data.data
}

export async function createClient(form: ClientForm) {
  const response = await apiClient.post<ApiItem<Client>>('/admin/clients', clientPayload(form))

  return response.data.data
}

export async function updateClient(id: number, form: ClientForm) {
  const response = await apiClient.put<ApiItem<Client>>(`/admin/clients/${id}`, clientPayload(form))

  return response.data.data
}

export async function deleteClient(id: number) {
  await apiClient.delete(`/admin/clients/${id}`)
}

export async function blacklistClient(id: number, raison: string) {
  const response = await apiClient.patch<ApiItem<Client>>(`/admin/clients/${id}/blacklist`, {
    raison: cleanText(raison),
  })

  return response.data.data
}

export async function unblacklistClient(id: number) {
  const response = await apiClient.patch<ApiItem<Client>>(`/admin/clients/${id}/unblacklist`, {})

  return response.data.data
}

export async function updateClientPreferences(id: number, form: ClientPreferencesForm) {
  const response = await apiClient.put<ApiItem<PreferenceClient>>(
    `/admin/clients/${id}/preferences`,
    preferencesPayload(form),
  )

  return response.data.data
}
