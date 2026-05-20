import { apiClient } from '../../lib/apiClient'
import type {
  ApiCollection,
  ApiItem,
  Client,
  LaravelPaginated,
} from '../admin/clients/clients.types'

type QueryParams = {
  page?: number
  search?: string
  per_page?: number
}

type GeranteClientForm = {
  nom: string
  prenom: string
  telephone: string
  email: string
}

function collection<T>(payload: ApiCollection<T>): LaravelPaginated<T> {
  return payload.data
}

function cleanText(value: string) {
  const text = value.trim()

  return text === '' ? null : text
}

function clientPayload(form: GeranteClientForm) {
  return {
    nom: form.nom.trim(),
    prenom: form.prenom.trim(),
    telephone: form.telephone.trim(),
    email: cleanText(form.email),
  }
}

export async function getGeranteClients(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<Client>>('/gerante/clients', {
    params,
  })

  return collection(response.data)
}

export async function getGeranteClient(id: number) {
  const response = await apiClient.get<ApiItem<Client>>(`/gerante/clients/${id}`)

  return response.data.data
}

export async function createGeranteClient(form: GeranteClientForm) {
  const response = await apiClient.post<ApiItem<Client>>('/gerante/clients', clientPayload(form))

  return response.data.data
}

export async function updateGeranteClient(id: number, form: GeranteClientForm) {
  const response = await apiClient.put<ApiItem<Client>>(`/gerante/clients/${id}`, clientPayload(form))

  return response.data.data
}

export type { GeranteClientForm }
