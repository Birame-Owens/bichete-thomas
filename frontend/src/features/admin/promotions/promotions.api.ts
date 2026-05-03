import { apiClient } from '../../../lib/apiClient'
import type {
  ApiCollection,
  ApiItem,
  CodePromo,
  CodePromoForm,
  LaravelPaginated,
  RegleFidelite,
  RegleFideliteForm,
} from './promotions.types'

type QueryParams = {
  page?: number
  search?: string
  actif?: boolean | string
  per_page?: number
}

function collection<T>(payload: ApiCollection<T>): LaravelPaginated<T> {
  return payload.data
}

function cleanText(value: string) {
  const text = value.trim()

  return text === '' ? null : text
}

function cleanDateTime(value: string) {
  return value.trim() === '' ? null : value
}

function cleanNumber(value: string) {
  return value.trim() === '' ? null : Number(value)
}

function normalizeCode(value: string) {
  return value.trim().toUpperCase().replace(/\s+/g, '')
}

function codePromoPayload(form: CodePromoForm) {
  return {
    code: normalizeCode(form.code),
    nom: cleanText(form.nom),
    type_reduction: form.type_reduction,
    valeur: Number(form.valeur || 0),
    date_debut: cleanDateTime(form.date_debut),
    date_fin: cleanDateTime(form.date_fin),
    limite_utilisation: cleanNumber(form.limite_utilisation),
    actif: form.actif,
  }
}

function regleFidelitePayload(form: RegleFideliteForm) {
  return {
    nom: form.nom.trim(),
    nombre_reservations_requis: Number(form.nombre_reservations_requis || 0),
    type_recompense: form.type_recompense,
    valeur_recompense: Number(form.valeur_recompense || 0),
    actif: form.actif,
  }
}

export async function getCodesPromo(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<CodePromo>>('/admin/codes-promo', {
    params,
  })

  return collection(response.data)
}

export async function createCodePromo(form: CodePromoForm) {
  const response = await apiClient.post<ApiItem<CodePromo>>('/admin/codes-promo', codePromoPayload(form))

  return response.data.data
}

export async function updateCodePromo(id: number, form: CodePromoForm) {
  const response = await apiClient.put<ApiItem<CodePromo>>(`/admin/codes-promo/${id}`, codePromoPayload(form))

  return response.data.data
}

export async function deleteCodePromo(id: number) {
  await apiClient.delete(`/admin/codes-promo/${id}`)
}

export async function getReglesFidelite(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<RegleFidelite>>('/admin/regles-fidelite', {
    params,
  })

  return collection(response.data)
}

export async function createRegleFidelite(form: RegleFideliteForm) {
  const response = await apiClient.post<ApiItem<RegleFidelite>>('/admin/regles-fidelite', regleFidelitePayload(form))

  return response.data.data
}

export async function updateRegleFidelite(id: number, form: RegleFideliteForm) {
  const response = await apiClient.put<ApiItem<RegleFidelite>>(`/admin/regles-fidelite/${id}`, regleFidelitePayload(form))

  return response.data.data
}

export async function deleteRegleFidelite(id: number) {
  await apiClient.delete(`/admin/regles-fidelite/${id}`)
}
