import { apiClient } from '../../../lib/apiClient'
import type { LaravelPaginated, LogQueryParams, LogSysteme } from './logs.types'

export async function getLogs(params: LogQueryParams = {}): Promise<LaravelPaginated<LogSysteme>> {
  const response = await apiClient.get<{ data: LaravelPaginated<LogSysteme> }>('/admin/logs-systeme', { params })
  return response.data.data
}
