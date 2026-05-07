import { apiClient } from '../../../lib/apiClient'
import type { ReportQueryParams, ReportsResponse } from './reports.types'

export async function getReports(params?: ReportQueryParams) {
  const response = await apiClient.get<ReportsResponse>('/admin/rapports-statistiques', {
    params,
  })

  return response.data
}
