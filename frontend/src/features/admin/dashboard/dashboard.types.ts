export type AvailabilityValue<T> = {
  available: boolean
  value?: T | null
  data?: T
  message?: string
  currency?: string
}

export type DashboardClient = {
  id: number
  nom: string
  prenom: string
  telephone?: string | null
  email?: string | null
  source?: string | null
  created_at?: string
}

export type DashboardExpense = {
  id: number
  titre?: string
  montant?: number | string
  date_depense?: string
  description?: string | null
}

export type DashboardApiResponse = {
  generated_at: string
  period: {
    today: string
    label?: string
  }
  cards: DashboardCard[]
  charts: {
    chiffre_affaires: AvailabilityValue<RevenuePoint[]>
    top_coiffures: AvailabilityValue<TopCoiffure[]>
  }
  lists: {
    reservations_du_jour: AvailabilityValue<DashboardReservation[]>
    dernieres_reservations: AvailabilityValue<DashboardReservation[]>
    activite_recente: AvailabilityValue<SystemLog[]>
    depenses_recentes: AvailabilityValue<DashboardExpense[]>
    clients_recents: AvailabilityValue<DashboardClient[]>
  }
  payments: {
    repartition: AvailabilityValue<PaymentMethod[]>
  }
  quick_payment: {
    available: boolean
    message?: string | null
    methods: string[]
  }
  promo: AvailabilityValue<PromoCode | null>
  kpis: {
    chiffre_affaires: AvailabilityValue<number>
    reservations_du_jour: AvailabilityValue<number>
    clients_total: AvailabilityValue<number>
    coiffures_total: AvailabilityValue<number>
    coiffeuses_actives: AvailabilityValue<number>
  }
  sections: {
    paiements_recents: AvailabilityValue<unknown[]>
    clients_recents: AvailabilityValue<DashboardClient[]>
    coiffures_plus_demandees: AvailabilityValue<unknown[]>
    coiffeuses_plus_productives: AvailabilityValue<unknown[]>
    depenses_recentes: AvailabilityValue<DashboardExpense[]>
  }
  modules_en_attente: string[]
}

export type SystemLog = {
  id: number
  action: string
  module?: string | null
  description?: string | null
  created_at?: string
}

export type DashboardCard = {
  key: string
  label: string
  available: boolean
  value?: number | null
  message?: string | null
  format: 'money' | 'number'
  color: string
  icon: string
  trend: string
}

export type RevenuePoint = {
  label: string
  date: string
  value: number
}

export type TopCoiffure = {
  name: string
  total: number
  percent: number
  color?: string
}

export type DashboardReservation = {
  id: number
  [key: string]: unknown
}

export type PaymentMethod = {
  method: string
  amount: number
  percent: number
}

export type PromoCode = {
  code?: string
  nom?: string | null
  valeur?: number | string
  type_reduction?: string
}

export type PaginatedResponse<T> = {
  data: {
    data: T[]
  }
}

export type DashboardTrendPoint = {
  label: string
  value: number
}

export type ReservationPreview = {
  id: number
  client: string
  coiffure: string
  heure: string
  montant: number
  statut: 'confirmee' | 'acompte_paye' | 'en_attente'
}

export type PaymentSplit = {
  methode: string
  montant: number
  percent: number
  color: string
}
