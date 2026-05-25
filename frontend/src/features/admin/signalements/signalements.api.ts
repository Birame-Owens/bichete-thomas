import { apiClient } from '../../../lib/apiClient'
import type { NonLusCountResponse, Signalement } from './signalements.types'

type PaginatedResponse<T> = { data: { data: T[]; total: number; current_page: number; last_page: number } }

export async function getAdminSignalements(params?: { traite?: boolean; urgence?: string }) {
  const response = await apiClient.get<PaginatedResponse<Signalement>>('/admin/signalements', { params })
  return response.data.data.data
}

export async function getNonLusCount() {
  const response = await apiClient.get<NonLusCountResponse>('/admin/signalements/non-lus-count')
  return response.data.count
}

export async function marquerLu(id: number) {
  const response = await apiClient.patch<{ data: Signalement }>(`/admin/signalements/${id}/marquer-lu`)
  return response.data.data
}

export async function marquerTraite(id: number, noteAdmin?: string) {
  const response = await apiClient.patch<{ data: Signalement }>(`/admin/signalements/${id}/marquer-traite`, {
    note_admin: noteAdmin ?? null,
  })
  return response.data.data
}
