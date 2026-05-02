import { apiClient } from '../../../lib/apiClient'
import type {
  DashboardApiResponse,
  PaginatedResponse,
  SystemLog,
} from './dashboard.types'

export const getAdminDashboard = async () => {
  const response = await apiClient.get<DashboardApiResponse>('/admin/dashboard')

  return response.data
}

export const getRecentSystemLogs = async () => {
  const response = await apiClient.get<PaginatedResponse<SystemLog>>(
    '/admin/logs-systeme',
    {
      params: {
        page: 1,
      },
    },
  )

  return response.data.data.data.slice(0, 5)
}
