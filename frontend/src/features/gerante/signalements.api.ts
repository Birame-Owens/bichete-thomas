import { apiClient } from '../../lib/apiClient'
import type { Signalement, SignalementForm } from '../admin/signalements/signalements.types'

type PaginatedResponse<T> = { data: { data: T[]; total: number; current_page: number; last_page: number } }

export async function getGeranteSignalements() {
  const response = await apiClient.get<PaginatedResponse<Signalement>>('/gerante/signalements')
  return response.data.data
}

export async function createSignalement(form: SignalementForm) {
  const response = await apiClient.post<{ message: string; data: Signalement }>('/gerante/signalements', form)
  return response.data.data
}
