import { apiClient } from '../../../lib/apiClient'
import type {
  ApiCollection,
  ApiItem,
  LaravelPaginated,
  SystemSetting,
} from './settings.types'

type QueryParams = {
  page?: number
  search?: string
  per_page?: number
}

function collection<T>(payload: ApiCollection<T>): LaravelPaginated<T> {
  return payload.data
}

export async function getSystemSettings(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<SystemSetting>>('/admin/parametres-systeme', {
    params,
  })

  return collection(response.data)
}

export async function updateSystemSetting(setting: SystemSetting, value: string | number | boolean | null) {
  const response = await apiClient.put<ApiItem<SystemSetting>>(`/admin/parametres-systeme/${setting.id}`, {
    valeur: value,
  })

  return response.data.data
}
