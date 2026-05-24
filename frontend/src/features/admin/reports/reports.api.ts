import { apiClient } from '../../../lib/apiClient'
import type { ReportQueryParams, ReportsResponse } from './reports.types'

export async function getReports(params?: ReportQueryParams) {
  const response = await apiClient.get<ReportsResponse>('/admin/rapports-statistiques', {
    params,
  })

  return response.data
}

export async function exportJournal(annee: number): Promise<void> {
  const response = await apiClient.get('/admin/rapports/export-journal', {
    params: { annee },
    responseType: 'blob',
  })

  const url = URL.createObjectURL(new Blob([response.data as BlobPart]))
  const link = document.createElement('a')
  link.href = url
  link.download = `journal-financier-${annee}.xlsx`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}
