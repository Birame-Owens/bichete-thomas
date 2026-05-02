import type { DashboardStats } from '../types/dashboard'
import { apiClient } from '../lib/apiClient'

export const getDashboardStats = async (token: string): Promise<DashboardStats> => {
  const response = await apiClient.get<DashboardStats>('/admin/dashboard', {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  })

  return response.data
}
