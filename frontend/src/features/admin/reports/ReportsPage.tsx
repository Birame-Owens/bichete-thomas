import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  Banknote,
  BarChart3,
  CalendarDays,
  CircleDollarSign,
  FileDown,
  PieChart,
  RefreshCw,
  Scissors,
  TrendingDown,
  TrendingUp,
  Users,
  WalletCards,
} from 'lucide-react'
import AdminLayout from '../../../layouts/AdminLayout'
import { exportJournal, getReports } from './reports.api'
import type {
  ReportCountBreakdown,
  ReportMetric,
  ReportMoneyBreakdown,
  ReportSeriesPoint,
  ReportTopItem,
  ReportsResponse,
} from './reports.types'
import {
  EmptyState,
  ErrorState,
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
} from '../payments/PaymentsUi'

const periodOptions = [
  { value: 'today', label: 'Aujourd hui' },
  { value: 'week', label: 'Cette semaine' },
  { value: 'month', label: 'Ce mois' },
  { value: 'year', label: 'Cette annee' },
  { value: 'custom', label: 'Personnalise' },
]

const chartColors = ['#e91e63', '#b719c9', '#f59e0b', '#10b981', '#6366f1', '#ef4444']

function money(value: number | string | null | undefined) {
  return `${Number(value ?? 0).toLocaleString('fr-FR')} FCFA`
}

function formatNumber(value: number | string | null | undefined) {
  return Number(value ?? 0).toLocaleString('fr-FR')
}

function formatMetric(metric: ReportMetric) {
  if (metric.format === 'money') {
    return money(metric.value)
  }

  if (metric.format === 'percent') {
    return `${formatNumber(metric.value)}%`
  }

  return formatNumber(metric.value)
}

function trendLabel(value: number) {
  const prefix = value > 0 ? '+' : ''

  return `${prefix}${value.toLocaleString('fr-FR')}% vs periode precedente`
}

function formatDate(value: string) {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

function MetricCard({
  label,
  metric,
  icon: Icon,
  inverseTrend = false,
}: {
  label: string
  metric: ReportMetric
  icon: typeof Banknote
  inverseTrend?: boolean
}) {
  const isPositive = metric.trend >= 0
  const goodTrend = inverseTrend ? !isPositive : isPositive
  const TrendIcon = isPositive ? TrendingUp : TrendingDown

  return (
    <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-xs font-black uppercase text-gray-400">{label}</p>
          <p className="mt-1 truncate text-2xl font-black text-[#111018]">{formatMetric(metric)}</p>
          <p className={`mt-2 inline-flex items-center gap-1 text-xs font-black ${goodTrend ? 'text-emerald-700' : 'text-red-600'}`}>
            <TrendIcon className="h-3.5 w-3.5" />
            {trendLabel(metric.trend)}
          </p>
        </div>
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#fff2f7] text-[#e91e63]">
          <Icon className="h-5 w-5" />
        </span>
      </div>
    </div>
  )
}

function SectionCard({
  title,
  children,
  action,
}: {
  title: string
  children: React.ReactNode
  action?: React.ReactNode
}) {
  return (
    <section className="rounded-xl border border-gray-100 bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
      <div className="mb-4 flex items-center justify-between gap-3">
        <h2 className="text-lg font-black text-[#111018]">{title}</h2>
        {action}
      </div>
      {children}
    </section>
  )
}

function RevenueExpenseChart({
  revenue,
  expenses,
}: {
  revenue: ReportSeriesPoint[]
  expenses: ReportSeriesPoint[]
}) {
  const expenseByKey = Object.fromEntries(expenses.map((e) => [e.key, e.value]))
  const points = revenue.map((point) => ({
    key: point.key,
    label: point.label,
    revenue: point.value,
    expense: expenseByKey[point.key] ?? 0,
  }))
  const max = Math.max(...points.flatMap((point) => [point.revenue, point.expense]), 1)

  if (points.length === 0) {
    return <EmptyState label="Aucune donnee pour cette periode." />
  }

  return (
    <div>
      <div className="mb-4 flex flex-wrap gap-3 text-xs font-bold text-gray-500">
        <span className="inline-flex items-center gap-2">
          <span className="h-2.5 w-2.5 rounded-full bg-[#e91e63]" />
          Chiffre d affaires
        </span>
        <span className="inline-flex items-center gap-2">
          <span className="h-2.5 w-2.5 rounded-full bg-[#f59e0b]" />
          Depenses
        </span>
      </div>
      <div className="overflow-x-auto pb-2">
        <div className="flex h-[240px] min-w-[720px] items-end gap-3 border-b border-l border-gray-100 px-2 pb-8">
          {points.map((point) => (
            <div key={point.key} className="relative flex min-w-[54px] flex-1 items-end justify-center gap-1">
              <span
                className="w-5 rounded-t-lg bg-gradient-to-t from-[#e91e63] to-[#ffd4e5]"
                style={{ height: `${Math.max((point.revenue / max) * 100, point.revenue > 0 ? 6 : 0)}%` }}
                title={money(point.revenue)}
              />
              <span
                className="w-5 rounded-t-lg bg-gradient-to-t from-[#f59e0b] to-[#fdecc8]"
                style={{ height: `${Math.max((point.expense / max) * 100, point.expense > 0 ? 6 : 0)}%` }}
                title={money(point.expense)}
              />
              <span className="absolute -bottom-7 left-1/2 w-16 -translate-x-1/2 truncate text-center text-[11px] font-bold text-gray-500">
                {point.label}
              </span>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

function ReservationsChart({ points }: { points: ReportSeriesPoint[] }) {
  const max = Math.max(...points.map((point) => point.value), 1)

  if (points.length === 0) {
    return <EmptyState label="Aucune reservation pour cette periode." />
  }

  return (
    <div className="space-y-3">
      {points.slice(-10).map((point) => (
        <div key={point.key} className="grid grid-cols-[70px_1fr_auto] items-center gap-3 text-sm">
          <span className="truncate font-bold text-gray-500">{point.label}</span>
          <span className="h-2.5 overflow-hidden rounded-full bg-gray-100">
            <span className="block h-full rounded-full bg-[#b719c9]" style={{ width: `${Math.max((point.value / max) * 100, point.value > 0 ? 4 : 0)}%` }} />
          </span>
          <span className="font-black text-gray-950">{formatNumber(point.value)}</span>
        </div>
      ))}
    </div>
  )
}

function MoneyBreakdownList({ items, emptyLabel }: { items: ReportMoneyBreakdown[]; emptyLabel: string }) {
  if (items.length === 0) {
    return <EmptyState label={emptyLabel} />
  }

  return (
    <div className="space-y-3">
      {items.map((item, index) => (
        <div key={item.key} className="space-y-1.5">
          <div className="flex items-center justify-between gap-3 text-sm">
            <span className="min-w-0 truncate font-black text-gray-900">{item.label}</span>
            <span className="shrink-0 font-bold text-gray-500">{money(item.amount)}</span>
          </div>
          <div className="grid grid-cols-[1fr_46px] items-center gap-3">
            <span className="h-2.5 overflow-hidden rounded-full bg-gray-100">
              <span
                className="block h-full rounded-full"
                style={{ width: `${Math.max(item.percent, item.amount > 0 ? 4 : 0)}%`, backgroundColor: chartColors[index % chartColors.length] }}
              />
            </span>
            <span className="text-right text-xs font-black text-gray-500">{item.percent}%</span>
          </div>
        </div>
      ))}
    </div>
  )
}

function CountBreakdownList({ items }: { items: ReportCountBreakdown[] }) {
  if (items.length === 0) {
    return <EmptyState label="Aucun statut pour cette periode." />
  }

  return (
    <div className="space-y-3">
      {items.map((item, index) => (
        <div key={item.key} className="space-y-1.5">
          <div className="flex items-center justify-between gap-3 text-sm">
            <span className="min-w-0 truncate font-black text-gray-900">{item.label}</span>
            <span className="shrink-0 font-bold text-gray-500">{formatNumber(item.count)} reservation(s)</span>
          </div>
          <span className="block h-2.5 overflow-hidden rounded-full bg-gray-100">
            <span
              className="block h-full rounded-full"
              style={{ width: `${Math.max(item.percent, item.count > 0 ? 4 : 0)}%`, backgroundColor: chartColors[index % chartColors.length] }}
            />
          </span>
        </div>
      ))}
    </div>
  )
}

function TopList({ items, emptyLabel }: { items: ReportTopItem[]; emptyLabel: string }) {
  if (items.length === 0) {
    return <EmptyState label={emptyLabel} />
  }

  return (
    <div className="space-y-2">
      {items.map((item, index) => (
        <div key={`${item.name}-${index}`} className="grid grid-cols-[34px_1fr_auto] items-center gap-3 rounded-lg border border-gray-100 px-3 py-2">
          <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[#fff2f7] text-sm font-black text-[#c41468]">
            {index + 1}
          </span>
          <div className="min-w-0">
            <p className="truncate text-sm font-black text-gray-950">{item.name}</p>
            <p className="text-xs font-bold text-gray-400">{item.reservations} reservation(s)</p>
          </div>
          <p className="text-right text-sm font-black text-[#c41468]">{money(item.amount)}</p>
        </div>
      ))}
    </div>
  )
}

function ReportsPage() {
  const [report, setReport] = useState<ReportsResponse | null>(null)
  const [period, setPeriod] = useState('month')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [exportAnnee, setExportAnnee] = useState(new Date().getFullYear())
  const [exporting, setExporting] = useState(false)
  const [exportError, setExportError] = useState<string | null>(null)

  const handleExport = useCallback(async () => {
    setExporting(true)
    setExportError(null)
    try {
      await exportJournal(exportAnnee)
    } catch {
      setExportError('Impossible de generer le fichier Excel. Reessayez.')
    } finally {
      setExporting(false)
    }
  }, [exportAnnee])

  const loadReport = useCallback(async () => {
    setLoading(true)
    setError(null)

    try {
      setReport(
        await getReports({
          period,
          date_debut: period === 'custom' ? dateFrom || undefined : undefined,
          date_fin: period === 'custom' ? dateTo || undefined : undefined,
        }),
      )
    } catch {
      setError('Impossible de charger les rapports et statistiques.')
    } finally {
      setLoading(false)
    }
  }, [dateFrom, dateTo, period])

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      void loadReport()
    }, period === 'custom' ? 250 : 0)

    return () => window.clearTimeout(timeoutId)
  }, [loadReport, period])

  const periodLabel = useMemo(() => {
    if (!report) {
      return ''
    }

    return `${report.period.label} - ${formatDate(report.period.date_debut)} au ${formatDate(report.period.date_fin)}`
  }, [report])

  return (
    <AdminLayout>
      <div className="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <p className="text-xs font-bold uppercase text-[#e91e63]">Pilotage salon</p>
          <h1 className="mt-2 text-2xl font-black text-[#111018] sm:text-3xl">Rapports & Statistiques</h1>
          <p className="mt-2 max-w-3xl text-sm font-medium text-gray-500">
            Analysez les revenus, depenses, reservations, paiements et performances du salon.
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row">
          <select value={period} onChange={(event) => setPeriod(event.target.value)} className={inputClass}>
            {periodOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
          {period === 'custom' && (
            <>
              <input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} className={inputClass} />
              <input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} className={inputClass} />
            </>
          )}
          <button type="button" onClick={() => void loadReport()} className={`${secondaryButtonClass} inline-flex items-center justify-center gap-2`}>
            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
            Actualiser
          </button>
        </div>
      </div>

      {/* Export Excel pour le Trésor */}
      <section className="mb-5 rounded-xl border border-[#f1e7ee] bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm font-black text-gray-950">Export pour le Tresor</p>
            <p className="mt-0.5 text-xs font-medium text-gray-400">
              Telecharge le journal financier annuel (journal journalier + recapitulatif mensuel + detail depenses).
            </p>
          </div>
          <div className="flex items-center gap-2">
            <select
              value={exportAnnee}
              onChange={(e) => setExportAnnee(Number(e.target.value))}
              className={inputClass}
              aria-label="Annee a exporter"
            >
              {Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i).map((y) => (
                <option key={y} value={y}>{y}</option>
              ))}
            </select>
            <button
              type="button"
              onClick={() => void handleExport()}
              disabled={exporting}
              className={`${primaryButtonClass} inline-flex min-w-[180px] items-center justify-center gap-2 disabled:opacity-60`}
            >
              <FileDown className={`h-4 w-4 ${exporting ? 'animate-bounce' : ''}`} />
              {exporting ? 'Generation...' : 'Exporter Excel'}
            </button>
          </div>
        </div>
        {exportError && (
          <p className="mt-2 text-xs font-bold text-red-600">{exportError}</p>
        )}
      </section>

      {error && <div className="mb-4"><ErrorState label={error} /></div>}

      {loading && !report ? (
        <div className="rounded-xl border border-gray-100 bg-white p-8 text-sm font-bold text-gray-500 shadow-sm">
          Chargement des rapports...
        </div>
      ) : report ? (
        <>
          <section className="mb-5 rounded-xl border border-[#f1e7ee] bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="text-sm font-black text-gray-950">{periodLabel}</p>
                <p className="mt-1 text-xs font-bold text-gray-400">
                  Compare a {formatDate(report.period.previous_date_debut)} - {formatDate(report.period.previous_date_fin)}
                  {' '}| Vue par {report.series.granularity === 'month' ? 'mois' : report.series.granularity === 'week' ? 'semaine' : 'jour'}
                </p>
              </div>
              <button type="button" onClick={() => window.print()} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}>
                <BarChart3 className="h-4 w-4" />
                Imprimer le rapport
              </button>
            </div>
          </section>

          <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Chiffre d affaires" metric={report.summary.chiffre_affaires} icon={CircleDollarSign} />
            <MetricCard label="Depenses" metric={report.summary.depenses} icon={WalletCards} inverseTrend />
            <MetricCard label="Benefice estime" metric={report.summary.benefice} icon={Banknote} />
            <MetricCard label="Reservations" metric={report.summary.reservations} icon={CalendarDays} />
            <MetricCard label="Nouveaux clients" metric={report.summary.nouveaux_clients} icon={Users} />
            <MetricCard label="Panier moyen" metric={report.summary.panier_moyen} icon={PieChart} />
            <MetricCard label="Taux annulation" metric={report.summary.taux_annulation} icon={TrendingDown} inverseTrend />
          </section>

          <section className="mb-5 grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(330px,0.55fr)]">
            <SectionCard title="Revenus vs depenses">
              <RevenueExpenseChart revenue={report.series.chiffre_affaires} expenses={report.series.depenses} />
            </SectionCard>
            <SectionCard title="Reservations par periode">
              <ReservationsChart points={report.series.reservations} />
            </SectionCard>
          </section>

          <section className="mb-5 grid gap-5 xl:grid-cols-3">
            <SectionCard title="Paiements par mode">
              <MoneyBreakdownList items={report.breakdowns.paiements_par_mode} emptyLabel="Aucun paiement valide pour cette periode." />
            </SectionCard>
            <SectionCard title="Depenses par categorie">
              <MoneyBreakdownList items={report.breakdowns.depenses_par_categorie} emptyLabel="Aucune depense pour cette periode." />
            </SectionCard>
            <SectionCard title="Reservations par statut">
              <CountBreakdownList items={report.breakdowns.reservations_par_statut} />
            </SectionCard>
          </section>

          <section className="grid gap-5 xl:grid-cols-3">
            <SectionCard title="Top coiffures" action={<Scissors className="h-5 w-5 text-[#e91e63]" />}>
              <TopList items={report.tops.coiffures} emptyLabel="Aucune coiffure reservee sur cette periode." />
            </SectionCard>
            <SectionCard title="Top clientes" action={<Users className="h-5 w-5 text-[#e91e63]" />}>
              <TopList items={report.tops.clients} emptyLabel="Aucune cliente classee sur cette periode." />
            </SectionCard>
            <SectionCard title="Top coiffeuses" action={<Scissors className="h-5 w-5 text-[#e91e63]" />}>
              <TopList items={report.tops.coiffeuses} emptyLabel="Aucune reservation terminee sur cette periode." />
            </SectionCard>
          </section>
        </>
      ) : null}
    </AdminLayout>
  )
}

export default ReportsPage
