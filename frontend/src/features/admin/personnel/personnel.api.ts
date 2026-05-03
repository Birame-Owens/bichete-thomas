import { apiClient } from '../../../lib/apiClient'
import type {
  ApiCollection,
  ApiItem,
  Coiffeuse,
  CoiffeuseForm,
  Gerante,
  GeranteForm,
  LaravelPaginated,
} from './personnel.types'

type QueryParams = {
  page?: number
  search?: string
  actif?: boolean | string
}

function collection<T>(payload: ApiCollection<T>): LaravelPaginated<T> {
  return payload.data
}

function cleanText(value: string) {
  const text = value.trim()

  return text === '' ? null : text
}

export async function getGerantes(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<Gerante>>('/admin/gerantes', {
    params,
  })

  return collection(response.data)
}

export async function createGerante(form: GeranteForm) {
  const response = await apiClient.post<ApiItem<Gerante>>('/admin/gerantes', {
    name: form.name.trim(),
    email: form.email.trim(),
    password: form.password,
    actif: form.actif,
  })

  return response.data.data
}

export async function updateGerante(id: number, form: GeranteForm) {
  const payload: Partial<GeranteForm> = {
    name: form.name.trim(),
    email: form.email.trim(),
    actif: form.actif,
  }

  if (form.password.trim() !== '') {
    payload.password = form.password
  }

  const response = await apiClient.put<ApiItem<Gerante>>(`/admin/gerantes/${id}`, payload)

  return response.data.data
}

export async function deleteGerante(id: number) {
  await apiClient.delete(`/admin/gerantes/${id}`)
}

export async function getCoiffeuses(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<Coiffeuse>>('/admin/coiffeuses', {
    params,
  })

  return collection(response.data)
}

export async function createCoiffeuse(form: CoiffeuseForm) {
  const response = await apiClient.post<ApiItem<Coiffeuse>>('/admin/coiffeuses', {
    nom: form.nom.trim(),
    prenom: form.prenom.trim(),
    telephone: cleanText(form.telephone),
    pourcentage_commission: Number(form.pourcentage_commission || 0),
    actif: form.actif,
  })

  return response.data.data
}

export async function updateCoiffeuse(id: number, form: CoiffeuseForm) {
  const response = await apiClient.put<ApiItem<Coiffeuse>>(`/admin/coiffeuses/${id}`, {
    nom: form.nom.trim(),
    prenom: form.prenom.trim(),
    telephone: cleanText(form.telephone),
    pourcentage_commission: Number(form.pourcentage_commission || 0),
    actif: form.actif,
  })

  return response.data.data
}

export async function deleteCoiffeuse(id: number) {
  await apiClient.delete(`/admin/coiffeuses/${id}`)
}
