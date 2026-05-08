export type ReportFormat = 'money' | 'number' | 'percent'

export type ReportMetric = {
  value: number
  previous: number
  format: ReportFormat
  trend: number
}

export type ReportSeriesPoint = {
  key: string
  label: string
  value: number
}

export type ReportMoneyBreakdown = {
  key: string
  label: string
  amount: number
  percent: number
}

export type ReportCountBreakdown = {
  key: string
  label: string
  count: number
  percent: number
}

export type ReportTopItem = {
  name: string
  reservations: number
  amount: number
}

export type ReportPeriod = {
  label: string
  date_debut: string
  date_fin: string
  previous_date_debut: string
  previous_date_fin: string
}

export type ReportsResponse = {
  generated_at: string
  period: ReportPeriod
  summary: {
    chiffre_affaires: ReportMetric
    depenses: ReportMetric
    benefice: ReportMetric
    reservations: ReportMetric
    nouveaux_clients: ReportMetric
    panier_moyen: ReportMetric
    taux_annulation: ReportMetric
  }
  series: {
    granularity: 'day' | 'week' | 'month'
    chiffre_affaires: ReportSeriesPoint[]
    depenses: ReportSeriesPoint[]
    reservations: ReportSeriesPoint[]
  }
  breakdowns: {
    paiements_par_mode: ReportMoneyBreakdown[]
    depenses_par_categorie: ReportMoneyBreakdown[]
    reservations_par_statut: ReportCountBreakdown[]
  }
  tops: {
    coiffures: ReportTopItem[]
    clients: ReportTopItem[]
    coiffeuses: ReportTopItem[]
  }
}

export type ReportQueryParams = {
  period?: string
  date_debut?: string
  date_fin?: string
}
